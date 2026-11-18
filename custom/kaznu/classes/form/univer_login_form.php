<?php
/**
 * Legacy Moodle form for separate Univer login page.
 *
 * @deprecated Since 1.0.0-portal-auth — not used. login_univer.php redirects to /login/index.php.
 * @todo Remove in next major after production cleanup window.
 * @package local_kaznu
 */

namespace local_kaznu\form;

defined('MOODLE_INTERNAL') || die();

require_once($GLOBALS['CFG']->libdir . '/formslib.php');

/**
 * @deprecated Not registered. Dual-login form removed from UX.
 */
class univer_login_form extends \moodleform {
    public function definition() {
        // Intentionally unused — keep empty definition for class autoload safety.
        $mform = $this->_form;
        $mform->addElement('static', 'deprecated', '', 'deprecated');
    }
}
