<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Install auth_univer — files only. Does NOT enable the auth plugin or migrate users.
 *
 * Explicit enable: local/kaznu/cli/enable_unified_login.php (after feature flag).
 *
 * @package auth_univer
 */

defined('MOODLE_INTERNAL') || die();

function xmldb_auth_univer_install() {
    // Intentionally empty of auth-chain / migration side effects.
    // Admin must enable via Site admin or CLI after staging validation.
    return true;
}
