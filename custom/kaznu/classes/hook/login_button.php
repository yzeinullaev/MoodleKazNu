<?php
namespace local_kaznu\hook;

use core\hook\output\before_standard_top_of_body_html_generation;

defined('MOODLE_INTERNAL') || die();

/**
 * @deprecated Dual-entry CTA removed. Not registered in db/hooks.php.
 * @todo Delete after production cleanup.
 */
final class login_button {
    public static function inject(before_standard_top_of_body_html_generation $hook): void {
        // No-op.
    }
}
