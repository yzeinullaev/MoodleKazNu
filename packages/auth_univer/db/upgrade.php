<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

/**
 * Upgrade auth_univer. Never auto-enables the plugin in $CFG->auth.
 *
 * @param int $oldversion
 * @return bool
 */
function xmldb_auth_univer_upgrade($oldversion) {
    // No schema; no auth enable; no user migration on upgrade.
    // Enable + nologin→univer migration is explicit CLI only.
    if ($oldversion < 2026080703) {
        upgrade_plugin_savepoint(true, 2026080703, 'auth', 'univer');
    }
    return true;
}
