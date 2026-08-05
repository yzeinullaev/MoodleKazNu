<?php
namespace local_kaznu\service;

use local_kaznu\api\univer_client;
use local_kaznu\api\univer_client_interface;
use local_kaznu\api\univer_exception;
use local_kaznu\event\univer_login_attempted;
use local_kaznu\local\login_rate_limiter;
use local_kaznu\local\login_result;

defined('MOODLE_INTERNAL') || die();

/**
 * Student Univer → Moodle login orchestration.
 *
 * Auth policy: users get auth='univer' (or nologin legacy) with random password hash
 * that cannot be used as a local Moodle password. Entry:
 *  - auth_univer loginpage_hook (preferred, single form) or
 *  - complete_user_login after login_service (internal).
 * Standard Moodle admin/manual auth is unaffected for other accounts.
 *
 * Phase 4 also syncs subjects + enrols; Phase 5 syncs cabinet modules.
 */
class login_service {
    public function __construct(
        private readonly ?univer_client_interface $client = null,
        private readonly ?user_service $userservice = null,
        private readonly ?login_rate_limiter $limiter = null,
        private readonly ?subject_service $subjectservice = null
    ) {
    }

    private function client(): univer_client_interface {
        return $this->client ?? new univer_client();
    }

    private function users(): user_service {
        return $this->userservice ?? new user_service();
    }

    private function limiter(): login_rate_limiter {
        return $this->limiter ?? new login_rate_limiter();
    }

    private function subjects(): subject_service {
        return $this->subjectservice ?? new subject_service();
    }

    /**
     * Authenticate against Univer and upsert Moodle user. Does NOT call complete_user_login.
     */
    public function authenticate(string $login, string $password, string $ip = ''): login_result {
        $correlation = bin2hex(random_bytes(8));
        $login = trim($login);

        if (!(int) get_config('local_kaznu', 'univer_enabled')) {
            $this->emit($correlation, 'disabled');
            return new login_result(login_result::DISABLED, null, false, $correlation, 'univer_login_disabled');
        }

        // Defense in depth: refuse credential handling without HTTPS wwwroot/request.
        if (!\local_kaznu\local\https_gate::is_secure() && !(defined('PHPUNIT_TEST') && PHPUNIT_TEST)
            && !(defined('BEHAT_SITE_RUNNING') && BEHAT_SITE_RUNNING)) {
            // Allow CLI smoke/tests via explicit override only.
            if (!(defined('CLI_SCRIPT') && CLI_SCRIPT && getenv('LOCAL_KAZNU_ALLOW_HTTP_LOGIN') === '1')) {
                $this->emit($correlation, 'https_required');
                return new login_result(login_result::DISABLED, null, false, $correlation, 'univer_https_required_login');
            }
        }

        if ($login === '' || $password === '') {
            $this->emit($correlation, 'empty');
            return new login_result(login_result::BAD_CREDENTIALS, null, false, $correlation, 'univer_login_empty');
        }

        $limiter = $this->limiter();
        if ($limiter->is_blocked($login, $ip)) {
            $this->emit($correlation, 'rate_limited');
            return new login_result(login_result::RATE_LIMITED, null, false, $correlation, 'univer_login_rate_limited');
        }

        $client = $this->client();
        try {
            $this->emit($correlation, 'attempt');

            try {
                $apilogin = $client->login($login, $password);
            } catch (univer_exception $e) {
                $limiter->register_failure($login, $ip);
                return $this->map_api_exception($e, $correlation);
            }

            if (!$apilogin->is_student()) {
                $limiter->register_failure($login, $ip);
                $this->emit($correlation, 'not_student');
                return new login_result(login_result::NOT_STUDENT, null, false, $correlation, 'univer_not_student');
            }
            if ($apilogin->univeruserid <= 0) {
                $limiter->register_failure($login, $ip);
                $this->emit($correlation, 'missing_userid');
                return new login_result(login_result::PROFILE_FAILED, null, false, $correlation, 'univer_profile_failed');
            }

            try {
                $profile = $client->get_profile_for_user($apilogin->univeruserid);
            } catch (univer_exception $e) {
                $limiter->register_failure($login, $ip);
                $this->emit($correlation, 'profile_failed');
                return new login_result(login_result::PROFILE_FAILED, null, false, $correlation, 'univer_profile_failed');
            }

            if ($profile->univeruserid <= 0) {
                $limiter->register_failure($login, $ip);
                $this->emit($correlation, 'profile_userid_missing');
                return new login_result(login_result::PROFILE_FAILED, null, false, $correlation, 'univer_profile_failed');
            }

            $semester = null;
            try {
                $semester = $client->get_semester_info();
            } catch (univer_exception $e) {
                // Semester is optional for login; continue without it.
                debugging('Univer semesterInfo skipped: ' . $e->errorcode, DEBUG_DEVELOPER);
            }

            try {
                $synced = $this->users()->upsert_from_univer($apilogin, $profile, $semester, []);
            } catch (univer_exception $e) {
                $limiter->register_failure($login, $ip);
                if ($e->errorcode === 'univer_username_conflict') {
                    $this->emit($correlation, 'conflict');
                    return new login_result(login_result::ACCOUNT_REVIEW, null, false, $correlation, 'univer_account_review');
                }
                $this->emit($correlation, 'sync_failed');
                return new login_result(login_result::SYNC_FAILED, null, false, $correlation, 'univer_sync_failed');
            } catch (\Throwable $e) {
                $limiter->register_failure($login, $ip);
                $this->emit($correlation, 'sync_failed');
                debugging('Univer sync failure correlation=' . $correlation, DEBUG_DEVELOPER);
                return new login_result(login_result::SYNC_FAILED, null, false, $correlation, 'univer_sync_failed');
            }

            // Phase 4: subjects + enrol while Univer session still valid (before finally).
            if ($semester) {
                try {
                    $this->subjects()->sync_from_client((int) $synced['user']->id, $client, $semester);
                } catch (\Throwable $e) {
                    // Login succeeds even if groupList/enrol fails; user can refresh later.
                    debugging('Univer subjects sync skipped: ' . get_class($e), DEBUG_DEVELOPER);
                }
                // Phase 5: schedule / assessments / attendance — isolated per module.
                try {
                    (new student_dashboard_service())->sync_live_modules((int) $synced['user']->id, $client, $semester);
                } catch (\Throwable $e) {
                    debugging('Univer cabinet modules sync skipped: ' . get_class($e), DEBUG_DEVELOPER);
                }
            }

            $limiter->register_success($login, $ip);
            $this->emit($correlation, 'success', (int) $synced['user']->id);
            return new login_result(
                login_result::OK,
                $synced['user'],
                (bool) $synced['created'],
                $correlation,
                ''
            );
        } finally {
            // Phase 3: destroy cookie jar; no vault.
            try {
                if (method_exists($client, 'logout')) {
                    $client->logout();
                }
            } catch (\Throwable $e) {
                // Ignore.
            }
            if (method_exists($client, 'get_cookie_jar')) {
                $client->get_cookie_jar()->clear();
            }
        }
    }

    /**
     * Complete Moodle session after a successful authenticate().
     */
    public function complete_login(\stdClass $user): void {
        complete_user_login($user);
        \core\session\manager::apply_concurrent_login_limit($user->id, session_id());
    }

    private function map_api_exception(univer_exception $e, string $correlation): login_result {
        $code = $e->errorcode;
        if (in_array($code, ['univer_bad_credentials', 'univer_login_empty', 'univer_auth_failed'], true)) {
            $this->emit($correlation, 'bad_credentials');
            return new login_result(login_result::BAD_CREDENTIALS, null, false, $correlation, 'univer_bad_credentials');
        }
        if ($code === 'univer_not_student') {
            $this->emit($correlation, 'not_student');
            return new login_result(login_result::NOT_STUDENT, null, false, $correlation, 'univer_not_student');
        }
        if (in_array($code, ['univer_http_error', 'univer_server_error', 'univer_not_found', 'univer_bad_response', 'univer_https_required'], true)) {
            $this->emit($correlation, 'unavailable');
            return new login_result(login_result::UNAVAILABLE, null, false, $correlation, 'univer_temporarily_unavailable');
        }
        $this->emit($correlation, 'auth_failed');
        return new login_result(login_result::BAD_CREDENTIALS, null, false, $correlation, 'univer_bad_credentials');
    }

    private function emit(string $correlationid, string $outcome, ?int $userid = null): void {
        try {
            univer_login_attempted::create_safe($correlationid, $outcome, $userid)->trigger();
        } catch (\Throwable $e) {
            // Events must never break login.
        }
    }
}
