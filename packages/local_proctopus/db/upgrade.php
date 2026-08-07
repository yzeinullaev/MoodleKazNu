<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_proctopus_upgrade($oldversion) {
    if ($oldversion < 2026080802) {
        if (get_config('local_proctopus', 'proctopus_legacy_mode') === false) {
            set_config('proctopus_legacy_mode', 1, 'local_proctopus');
        }
        if (get_config('local_proctopus', 'proctopus_live_calls_enabled') === false) {
            set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');
        }
        if (get_config('local_proctopus', 'proctopus_start_path') === false) {
            set_config('proctopus_start_path', '/integration/simple/farabi_business/start/', 'local_proctopus');
        }
        if (get_config('local_proctopus', 'proctopus_jwt_algorithm') === false
                || get_config('local_proctopus', 'proctopus_jwt_algorithm') === '') {
            set_config('proctopus_jwt_algorithm', 'HS256', 'local_proctopus');
        }
        if (get_config('local_proctopus', 'proctopus_baseurl') === false
                || get_config('local_proctopus', 'proctopus_baseurl') === '') {
            set_config('proctopus_baseurl', 'https://farabi-proctoring.kaznu.kz', 'local_proctopus');
        }
        // Never enable flags or write secrets.
        upgrade_plugin_savepoint(true, 2026080802, 'local', 'proctopus');
    }
    if ($oldversion < 2026081002) {
        // Preparation UX: check.php + student strings. No schema change.
        upgrade_plugin_savepoint(true, 2026081002, 'local', 'proctopus');
    }
    return true;
}
