<?php
defined('MOODLE_INTERNAL') || die();

/**
 * @param int $oldversion
 * @return bool
 */
function xmldb_local_kaznu_upgrade($oldversion) {
    global $CFG, $DB;

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
    if ($oldversion < 2026072211) {
        upgrade_plugin_savepoint(true, 2026072211, 'local', 'kaznu');
    }
    if ($oldversion < 2026072412) {
        upgrade_plugin_savepoint(true, 2026072412, 'local', 'kaznu');
    }
    if ($oldversion < 2026072413) {
        upgrade_plugin_savepoint(true, 2026072413, 'local', 'kaznu');
    }
    if ($oldversion < 2026072414) {
        upgrade_plugin_savepoint(true, 2026072414, 'local', 'kaznu');
    }

    if ($oldversion < 2026072420) {
        require_once(__DIR__ . '/../locallib.php');
        local_kaznu_retire_summer_course();
        upgrade_plugin_savepoint(true, 2026072420, 'local', 'kaznu');
    }

    if ($oldversion < 2026072423) {
        require_once(__DIR__ . '/../locallib.php');
        local_kaznu_fix_url_video_embed_display();
        upgrade_plugin_savepoint(true, 2026072423, 'local', 'kaznu');
    }

    if ($oldversion < 2026072429) {
        require_once(__DIR__ . '/../locallib.php');
        local_kaznu_fix_resource_pdf_embed_display();
        upgrade_plugin_savepoint(true, 2026072429, 'local', 'kaznu');
    }

    if ($oldversion < 2026073002) {
        // Univer user cache (no secrets).
        $table = new xmldb_table('local_kaznu_univer_user');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('univer_userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('univer_login', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('faculty', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('speciality', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('course_number', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('group_name', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('current_year', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('current_semester', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('email_is_technical', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '1');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('lastsync', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('useriduk', XMLDB_KEY_UNIQUE, ['userid']);
        $table->add_key('univeruseriduk', XMLDB_KEY_UNIQUE, ['univer_userid']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('statusix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('lastsyncix', XMLDB_INDEX_NOTUNIQUE, ['lastsync']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Univer subject cache.
        $table = new xmldb_table('local_kaznu_univer_subject');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('subjectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academyear', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academsemester', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('course_idnumber', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('subjectname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('groupid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('planposid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('eductypeid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('eductypename', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'active');
        $table->add_field('moodlecourseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('enrolled', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('firstseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastseen', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usersubjectuk', XMLDB_KEY_UNIQUE, ['userid', 'subjectid', 'academyear', 'academsemester']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('idnumberix', XMLDB_INDEX_NOTUNIQUE, ['course_idnumber']);
        $table->add_index('statusix', XMLDB_INDEX_NOTUNIQUE, ['status']);
        $table->add_index('lastseenix', XMLDB_INDEX_NOTUNIQUE, ['lastseen']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        upgrade_plugin_savepoint(true, 2026073002, 'local', 'kaznu');
    }

    if ($oldversion < 2026073003) {
        // Phase 3: caches + hooks registration via upgrade savepoint.
        upgrade_plugin_savepoint(true, 2026073003, 'local', 'kaznu');
    }

    if ($oldversion < 2026073004) {
        // Raise production-friendly IP rate-limit default if still at early 30.
        $ip = (int) get_config('local_kaznu', 'univer_rl_ip');
        if ($ip > 0 && $ip < 100) {
            set_config('univer_rl_ip', 100, 'local_kaznu');
        }
        // Keep Univer off until HTTPS wwwroot is configured.
        if (!str_starts_with(strtolower($CFG->wwwroot ?? ''), 'https://')) {
            set_config('univer_enabled', 0, 'local_kaznu');
        }
        upgrade_plugin_savepoint(true, 2026073004, 'local', 'kaznu');
    }

    if ($oldversion < 2026080401) {
        $table = new xmldb_table('local_kaznu_sync_log');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('action', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('result', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('subjectid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('detail', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('useractionix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'action']);
        $table->add_index('timeix', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        upgrade_plugin_savepoint(true, 2026080401, 'local', 'kaznu');
    }

    if ($oldversion < 2026080501) {
        // Phase 5 cabinet cache tables.
        $tables = [];

        $table = new xmldb_table('local_kaznu_schedule');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('itemtype', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'lesson');
        $table->add_field('academyear', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academsemester', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('external_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('subjectname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('lesson_type', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('teacher', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('building', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('audience', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('weekday', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timebegin', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('timeend', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('datefrom', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('dateto', XMLDB_TYPE_CHAR, '32', null, XMLDB_NOTNULL, null, '');
        $table->add_field('eventdate', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('eventtime', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('denominator', XMLDB_TYPE_INTEGER, '2', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('apitype', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('lastsynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usertypekeyuk', XMLDB_KEY_UNIQUE, ['userid', 'itemtype', 'academyear', 'academsemester', 'external_key']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userperiodix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'itemtype', 'academyear', 'academsemester']);
        $table->add_index('eventdateix', XMLDB_INDEX_NOTUNIQUE, ['eventdate']);
        $table->add_index('lastsyncedix', XMLDB_INDEX_NOTUNIQUE, ['lastsynced']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_kaznu_assessment');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academyear', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academsemester', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('external_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('subjectname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('credit', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('resultrk1', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('resultrk2', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('result', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('totalres', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('gpa', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('symbol', XMLDB_TYPE_CHAR, '10', null, XMLDB_NOTNULL, null, '');
        $table->add_field('lettertext', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('lastsynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userkeyuk', XMLDB_KEY_UNIQUE, ['userid', 'academyear', 'academsemester', 'external_key']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userperiodix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'academyear', 'academsemester']);
        $table->add_index('lastsyncedix', XMLDB_INDEX_NOTUNIQUE, ['lastsynced']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_kaznu_attendance');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academyear', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academsemester', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('external_key', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('subjectname', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('label', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, '');
        $table->add_field('period', XMLDB_TYPE_CHAR, '100', null, XMLDB_NOTNULL, null, '');
        $table->add_field('groupinfo', XMLDB_TYPE_CHAR, '64', null, XMLDB_NOTNULL, null, '');
        $table->add_field('rate1', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('rate2', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, '');
        $table->add_field('lastsynced', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('userkeyuk', XMLDB_KEY_UNIQUE, ['userid', 'academyear', 'academsemester', 'external_key']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('userperiodix', XMLDB_INDEX_NOTUNIQUE, ['userid', 'academyear', 'academsemester']);
        $table->add_index('lastsyncedix', XMLDB_INDEX_NOTUNIQUE, ['lastsynced']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_kaznu_cache_state');
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('module', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('academyear', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('academsemester', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastsuccess', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lastattempt', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('lasterror', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('last_error_code', XMLDB_TYPE_CHAR, '40', null, XMLDB_NOTNULL, null, '');
        $table->add_field('rowcount', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('durationms', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('usermoduleuk', XMLDB_KEY_UNIQUE, ['userid', 'module', 'academyear', 'academsemester']);
        $table->add_key('useridfk', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);
        $table->add_index('lastsuccessix', XMLDB_INDEX_NOTUNIQUE, ['lastsuccess']);
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // TTL defaults (seconds) + retention days.
        if (get_config('local_kaznu', 'univer_schedule_ttl') === false) {
            set_config('univer_schedule_ttl', 900, 'local_kaznu');
        }
        if (get_config('local_kaznu', 'univer_assessment_ttl') === false) {
            set_config('univer_assessment_ttl', 1800, 'local_kaznu');
        }
        if (get_config('local_kaznu', 'univer_attendance_ttl') === false) {
            set_config('univer_attendance_ttl', 1800, 'local_kaznu');
        }
        if (get_config('local_kaznu', 'univer_cache_retention_days') === false) {
            set_config('univer_cache_retention_days', 180, 'local_kaznu');
        }

        upgrade_plugin_savepoint(true, 2026080501, 'local', 'kaznu');
    }

    if ($oldversion < 2026080601) {
        // Phase 6A/6C: portal role, guest/blogs site policy, assign linked students.
        update_capabilities('local_kaznu');

        // Snapshot previous site config for rollback (actual prior values).
        global $CFG;
        if (get_config('local_kaznu', 'portal_prev_theme') === false) {
            set_config('portal_prev_theme', (string) ($CFG->theme ?? 'boost'), 'local_kaznu');
        }
        if (get_config('local_kaznu', 'portal_prev_guestloginbutton') === false) {
            set_config('portal_prev_guestloginbutton', (string) ($CFG->guestloginbutton ?? '1'), 'local_kaznu');
        }
        if (get_config('local_kaznu', 'portal_prev_allowguestmymoodle') === false) {
            set_config('portal_prev_allowguestmymoodle', (string) ($CFG->allowguestmymoodle ?? '1'), 'local_kaznu');
        }
        if (get_config('local_kaznu', 'portal_prev_autologinguests') === false) {
            set_config('portal_prev_autologinguests', (string) ($CFG->autologinguests ?? '0'), 'local_kaznu');
        }
        if (get_config('local_kaznu', 'portal_prev_enableblogs') === false) {
            set_config('portal_prev_enableblogs', (string) ($CFG->enableblogs ?? '1'), 'local_kaznu');
        }
        if (get_config('local_kaznu', 'portal_prev_customusermenuitems') === false) {
            set_config('portal_prev_customusermenuitems', (string) ($CFG->customusermenuitems ?? ''), 'local_kaznu');
        }

        // Confirm no blog posts / business use before disable (rowcount logged).
        $blogcount = 0;
        if ($dbman->table_exists('post')) {
            $blogcount = (int) $DB->count_records_select('post', "module = :m", ['m' => 'blog']);
        }
        set_config('portal_blog_posts_at_disable', (string) $blogcount, 'local_kaznu');

        // Guest permanently off (system guest user retained).
        set_config('guestloginbutton', 0);
        set_config('allowguestmymoodle', 0);
        set_config('autologinguests', 0);

        // Blogs off site-wide.
        set_config('enableblogs', 0);

        if (get_config('local_kaznu', 'portal_usermenu_initialized') === false) {
            set_config('portal_usermenu_initialized', 1, 'local_kaznu');
        }

        \local_kaznu\local\portal_role::ensure_role();
        // Only linked Univer users — never all manual users.
        \local_kaznu\local\portal_role::assign_all_linked();

        upgrade_plugin_savepoint(true, 2026080601, 'local', 'kaznu');
    }

    if ($oldversion < 2026080701) {
        // Phase 6B: dual-entry login CTA removed; auth_univer provides single form.
        // No schema change. login_univer.php is redirect-only.
        upgrade_plugin_savepoint(true, 2026080701, 'local', 'kaznu');
    }

    if ($oldversion < 2026080702) {
        // Phase 6 RC 1.0.0-portal-auth: cabinet tabs + nav cohesion (no schema change).
        upgrade_plugin_savepoint(true, 2026080702, 'local', 'kaznu');
    }

    if ($oldversion < 2026080703) {
        // Staging APPROVE WITH CHANGES: caps, portal roles Variant A, kill switches default off.
        update_capabilities('local_kaznu');

        if (get_config('local_kaznu', 'univer_unified_login_enabled') === false) {
            set_config('univer_unified_login_enabled', 0, 'local_kaznu');
        }
        if (get_config('local_kaznu', 'univer_fallback_auth_methods') === false) {
            set_config('univer_fallback_auth_methods', 'manual,email', 'local_kaznu');
        }

        \local_kaznu\local\portal_role::ensure_all_roles();
        // Limited: ordinary non-staff accounts + linked Univer (never siteadmin / managers).
        \local_kaznu\local\portal_role::assign_ordinary_portal_users();
        // Never enables auth_univer — explicit CLI only.

        upgrade_plugin_savepoint(true, 2026080703, 'local', 'kaznu');
    }

    if ($oldversion < 2026080704) {
        // Portal primary nav: hide Home/Dashboard/My courses for portal users (hook registered).
        upgrade_plugin_savepoint(true, 2026080704, 'local', 'kaznu');
    }

    return true;
}
