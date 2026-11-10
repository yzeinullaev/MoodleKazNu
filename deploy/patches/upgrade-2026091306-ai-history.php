    if ($oldversion < 2026091306) {
        // GPT-style AI chat history (threads in DB).
        $table = new xmldb_table('local_kaznu_conversation');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('title', XMLDB_TYPE_CHAR, '255', null, XMLDB_NOTNULL, null, 'Новый чат');
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('deleted', XMLDB_TYPE_INTEGER, '1', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('useridmod', XMLDB_INDEX_NOTUNIQUE, ['userid', 'timemodified']);
            $dbman->create_table($table);
        }

        $table = new xmldb_table('local_kaznu_message');
        if (!$dbman->table_exists($table)) {
            $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
            $table->add_field('conversationid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_field('role', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, 'user');
            $table->add_field('content', XMLDB_TYPE_TEXT, null, null, XMLDB_NOTNULL, null, null);
            $table->add_field('metadata', XMLDB_TYPE_TEXT, null, null, null, null, null);
            $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, '0');
            $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
            $table->add_index('convtime', XMLDB_INDEX_NOTUNIQUE, ['conversationid', 'timecreated']);
            $dbman->create_table($table);
        }

        if (get_config('local_kaznu', 'enable_web_search') === false) {
            set_config('enable_web_search', 1, 'local_kaznu');
        }
        if (get_config('local_kaznu', 'max_history') === false) {
            set_config('max_history', 40, 'local_kaznu');
        }

        upgrade_plugin_savepoint(true, 2026091306, 'local', 'kaznu');
    }
