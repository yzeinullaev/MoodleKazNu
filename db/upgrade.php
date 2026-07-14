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
    if ($oldversion < 2026052104) {
        upgrade_plugin_savepoint(true, 2026052104, 'local', 'kaznu');
    }
    if ($oldversion < 2026052107) {
        upgrade_plugin_savepoint(true, 2026052107, 'local', 'kaznu');
    }
    if ($oldversion < 2026071401) {
        upgrade_plugin_savepoint(true, 2026071401, 'local', 'kaznu');
    }
    return true;
}
