<?php
defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kaznu_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

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
    if ($oldversion < 2026071701) {
        upgrade_plugin_savepoint(true, 2026071701, 'local', 'kaznu');
    }

    if ($oldversion < 2026072201) {
        $table = new xmldb_table('local_kaznu_xp');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('xp', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('level', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('badges', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('useriduk', XMLDB_KEY_UNIQUE, ['userid']);

        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026072201, 'local', 'kaznu');
    }

    if ($oldversion < 2026072202) {
        upgrade_plugin_savepoint(true, 2026072202, 'local', 'kaznu');
    }
    if ($oldversion < 2026072203) {
        upgrade_plugin_savepoint(true, 2026072203, 'local', 'kaznu');
    }
    if ($oldversion < 2026072204) {
        upgrade_plugin_savepoint(true, 2026072204, 'local', 'kaznu');
    }
    if ($oldversion < 2026072205) {
        upgrade_plugin_savepoint(true, 2026072205, 'local', 'kaznu');
    }
    if ($oldversion < 2026072206) {
        upgrade_plugin_savepoint(true, 2026072206, 'local', 'kaznu');
    }
    if ($oldversion < 2026072207) {
        upgrade_plugin_savepoint(true, 2026072207, 'local', 'kaznu');
    }
    if ($oldversion < 2026072208) {
        upgrade_plugin_savepoint(true, 2026072208, 'local', 'kaznu');
    }
    if ($oldversion < 2026072209) {
        upgrade_plugin_savepoint(true, 2026072209, 'local', 'kaznu');
    }
    if ($oldversion < 2026072210) {
        upgrade_plugin_savepoint(true, 2026072210, 'local', 'kaznu');
    }

    return true;
}
