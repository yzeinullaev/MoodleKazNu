<?php
defined('MOODLE_INTERNAL') || die();

function xmldb_local_proctopus_install(): void {
    set_config('proctopus_enabled', 0, 'local_proctopus');
    set_config('proctopus_legacy_mode', 1, 'local_proctopus');
    set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');
    set_config('proctopus_baseurl', 'https://farabi-proctoring.kaznu.kz', 'local_proctopus');
    set_config('proctopus_account_name', 'farabi_business', 'local_proctopus');
    set_config('proctopus_start_path', '/integration/simple/farabi_business/start/', 'local_proctopus');
    set_config('proctopus_callback_url', 'https://farabischool.kz/proctoring/proctopus/result', 'local_proctopus');
    set_config('proctopus_jwt_algorithm', 'HS256', 'local_proctopus');
    set_config('proctopus_debug', 0, 'local_proctopus');
    set_config('proctopus_issuer', 'farabischool.kz', 'local_proctopus');
}
