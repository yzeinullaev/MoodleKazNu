<?php
// This file is part of Moodle - http://moodle.org/

namespace theme_farabi\output;

defined('MOODLE_INTERNAL') || die();

/**
 * Renderer overrides for portal student user menu.
 *
 * @package   theme_farabi
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class core_renderer extends \theme_boost\output\core_renderer {

    /**
     * Restrict avatar dropdown for portal students.
     *
     * @param \stdClass|null $user
     * @param bool|null $withlinks
     * @return string
     */
    public function user_menu($user = null, $withlinks = null) {
        global $CFG, $USER;

        $target = $user ?? $USER;
        if (!isloggedin() || isguestuser($target)) {
            return parent::user_menu($user, $withlinks);
        }

        if (!class_exists(\local_kaznu\access\portal_policy::class)) {
            return parent::user_menu($user, $withlinks);
        }

        if (!\local_kaznu\access\portal_policy::is_portal_user($target)) {
            return parent::user_menu($user, $withlinks);
        }

        // Minimal student menu: cabinet + profile + logout (logout added by core).
        $saved = $CFG->customusermenuitems ?? '';
        $CFG->customusermenuitems =
            "nav_my_cabinet,local_kaznu|" . (new \moodle_url('/local/kaznu/my.php'))->out(false) . "\n" .
            "profile,moodle|/user/profile.php";
        try {
            return parent::user_menu($user, $withlinks);
        } finally {
            $CFG->customusermenuitems = $saved;
        }
    }
}
