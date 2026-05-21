<?php
defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kaznu_upgrade($oldversion) {
    if ($oldversion < 2026052103) {
        update_capabilities('local_kaznu');
        upgrade_plugin_savepoint(true, 2026052103, 'local', 'kaznu');
    }
    return true;
}
