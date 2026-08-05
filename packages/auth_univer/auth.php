<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Authentication plugin: Univer-first → controlled Moodle fallback.
 *
 * Guarded by local_kaznu/univer_unified_login_enabled (default off).
 * Plugin presence does NOT enable the flow; admin must enable flag + auth plugin.
 *
 * Success path: complete_user_login once + redirect (core form flow abandoned).
 * Soft fail: keep password only when local fallback is allowed.
 *
 * @package   auth_univer
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/authlib.php');

/**
 * Farabi unified authentication plugin.
 */
class auth_plugin_univer extends auth_plugin_base {

    /** @var bool Prevent re-entry / double session. */
    private static bool $processing = false;

    public function __construct() {
        $this->authtype = 'univer';
        $this->config = get_config('auth_univer') ?: new stdClass();
    }

    /**
     * Called by Moodle when user.auth = univer.
     * Never accepts a local password hash — only live Univer API via login_service.
     *
     * @param string $username
     * @param string $password
     * @return bool
     */
    public function user_login($username, $password) {
        if (self::$processing) {
            return false;
        }
        if (!$this->unified_login_active()) {
            return false;
        }
        if (!class_exists(\local_kaznu\service\login_service::class)) {
            return false;
        }
        $service = new \local_kaznu\service\login_service();
        $result = $service->authenticate($username, $password, getremoteaddr());
        return $result->is_success();
    }

    /**
     * Univer-first intercept. On SUCCESS: complete_user_login + redirect (no return to core).
     * On soft fail: leave $frm for core only if local_fallback_allowed().
     * On hard fail: clear password, notify, redirect (no Moodle fallback).
     */
    public function loginpage_hook() {
        global $frm, $user;

        if (self::$processing || during_initial_install()) {
            return;
        }
        if (!$this->unified_login_active()) {
            return;
        }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            return;
        }
        if (empty($frm) || !is_object($frm)) {
            return;
        }

        $username = isset($frm->username) ? trim((string) $frm->username) : '';
        $password = isset($frm->password) ? (string) $frm->password : '';
        if ($username === '' || $password === '') {
            return;
        }
        if (!class_exists(\local_kaznu\service\login_service::class)) {
            return;
        }

        self::$processing = true;
        try {
            $service = new \local_kaznu\service\login_service();
            $result = $service->authenticate($username, $password, getremoteaddr());

            if ($result->is_success()) {
                // Abandon core authenticate_user_login — one session only.
                $this->wipe_password_materials($frm);
                $this->ensure_user_auth_univer((int) $result->user->id);
                $service->complete_login($result->user);
                $user = $result->user;
                redirect($this->resolve_wantsurl((int) $result->user->id));
                // Unreachable.
            }

            // HARD FAIL: never Moodle fallback.
            $hard = [
                \local_kaznu\local\login_result::ACCOUNT_REVIEW,
                \local_kaznu\local\login_result::SYNC_FAILED,
                \local_kaznu\local\login_result::PROFILE_FAILED,
                \local_kaznu\local\login_result::DISABLED,
                \local_kaznu\local\login_result::RATE_LIMITED,
            ];
            if (in_array($result->status, $hard, true)) {
                $this->wipe_password_materials($frm);
                \core\notification::error($this->neutral_error($result));
                redirect(new moodle_url('/login/index.php'));
            }

            // UNAVAILABLE: only configured local fallback methods for known local users.
            if ($result->status === \local_kaznu\local\login_result::UNAVAILABLE) {
                if (!$this->local_fallback_allowed($username)) {
                    $this->wipe_password_materials($frm);
                    \core\notification::error(get_string('auth_univer_unavailable', 'auth_univer'));
                    redirect(new moodle_url('/login/index.php'));
                }
                // Keep password for core; return from hook.
                return;
            }

            // BAD_CREDENTIALS | NOT_STUDENT: fall through only if not a Univer-bound identity
            // and local auth is in the configured allowlist (prevents foreign local takeover).
            if (!$this->local_fallback_allowed($username)) {
                $this->wipe_password_materials($frm);
                // Same UI as core invalidlogin (no enumeration of which backend failed).
                \core\notification::error(get_string('auth_univer_invalidlogin', 'auth_univer'));
                redirect(new moodle_url('/login/index.php'));
            }
            // Keep $frm->password for Moodle — do not clear.
        } finally {
            self::$processing = false;
        }
    }

    /**
     * Feature flag + plugin + integration switches.
     */
    private function unified_login_active(): bool {
        if (!(int) get_config('local_kaznu', 'univer_unified_login_enabled')) {
            return false;
        }
        if (!(int) get_config('local_kaznu', 'univer_enabled')) {
            return false;
        }
        // Auth plugin must be in enabled list (explicit enable step).
        $enabled = get_enabled_auth_plugins(true);
        return in_array('univer', $enabled, true);
    }

    /**
     * Whether Moodle may try local password for this username.
     * Unknown users: false (do not fan-out auth plugins for attack surface).
     * auth=univer / nologin / linked: false.
     * Only allowlisted methods from local_kaznu/univer_fallback_auth_methods.
     */
    public function local_fallback_allowed(string $username): bool {
        global $CFG, $DB;
        $username = \core_text::strtolower(trim($username));
        if ($username === '') {
            return false;
        }
        $user = $DB->get_record('user', [
            'username' => $username,
            'mnethostid' => $CFG->mnet_localhost_id,
            'deleted' => 0,
        ], 'id,auth,suspended');
        if (!$user || (int) $user->suspended === 1) {
            return false;
        }
        $auth = (string) $user->auth;
        // Never local password for external-bound portal accounts.
        if (in_array($auth, ['univer', 'nologin'], true)) {
            return false;
        }
        if ($DB->record_exists('local_kaznu_univer_user', ['userid' => (int) $user->id])) {
            return false;
        }
        $allowed = $this->fallback_auth_methods();
        if (!in_array($auth, $allowed, true)) {
            return false;
        }
        // Method must also be a currently enabled Moodle auth plugin.
        $enabled = get_enabled_auth_plugins(true);
        return in_array($auth, $enabled, true);
    }

    /**
     * @return string[]
     */
    private function fallback_auth_methods(): array {
        $raw = (string) (get_config('local_kaznu', 'univer_fallback_auth_methods') ?: 'manual,email');
        $parts = array_filter(array_map('trim', explode(',', \core_text::strtolower($raw))));
        // Never allow univer/nologin as "local" fallback.
        $parts = array_values(array_diff($parts, ['univer', 'nologin', '']));
        return $parts ?: ['manual', 'email'];
    }

    private function wipe_password_materials(stdClass $frm): void {
        $frm->password = '';
        if (isset($_POST['password'])) {
            $_POST['password'] = '';
        }
    }

    private function ensure_user_auth_univer(int $userid): void {
        global $DB;
        if ($userid <= 0 || is_siteadmin($userid)) {
            return;
        }
        $auth = (string) $DB->get_field('user', 'auth', ['id' => $userid]);
        if ($auth === 'univer') {
            return;
        }
        if (in_array($auth, ['nologin', ''], true)) {
            $DB->set_field('user', 'auth', 'univer', ['id' => $userid]);
            return;
        }
        if ($DB->record_exists('local_kaznu_univer_user', ['userid' => $userid])
                && $auth !== 'manual') {
            $DB->set_field('user', 'auth', 'univer', ['id' => $userid]);
        }
    }

    private function resolve_wantsurl(int $userid): moodle_url {
        global $SESSION;
        if (!empty($SESSION->wantsurl)) {
            $raw = $SESSION->wantsurl;
            unset($SESSION->wantsurl);
            if (class_exists(\local_kaznu\local\return_url::class)) {
                return \local_kaznu\local\return_url::sanitize($raw);
            }
        }
        $user = \core_user::get_user($userid);
        if ($user && class_exists(\local_kaznu\access\portal_policy::class)
                && \local_kaznu\access\portal_policy::is_portal_user($user)) {
            return new moodle_url('/local/kaznu/my.php');
        }
        if ($user && $GLOBALS['DB']->record_exists('local_kaznu_univer_user', ['userid' => $userid])) {
            return new moodle_url('/local/kaznu/my.php');
        }
        return new moodle_url('/my/');
    }

    private function neutral_error(\local_kaznu\local\login_result $result): string {
        return match ($result->status) {
            \local_kaznu\local\login_result::RATE_LIMITED => get_string('auth_univer_rate_limited', 'auth_univer'),
            \local_kaznu\local\login_result::DISABLED => get_string('auth_univer_disabled', 'auth_univer'),
            \local_kaznu\local\login_result::UNAVAILABLE => get_string('auth_univer_unavailable', 'auth_univer'),
            default => get_string('auth_univer_invalidlogin', 'auth_univer'),
        };
    }

    public function can_change_password() {
        return false;
    }

    public function change_password_url() {
        return null;
    }

    public function can_reset_password() {
        return false;
    }

    public function is_internal() {
        return false;
    }

    public function prevent_local_passwords() {
        return true;
    }

    public function is_synchronised_with_external() {
        return true;
    }

    public function user_update_password($user, $newpassword) {
        return false;
    }

    public function loginpage_idp_list($wantsurl) {
        return [];
    }
}
