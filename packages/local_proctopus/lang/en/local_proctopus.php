<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Proctopus proctoring (Farabi)';
$string['settings_flags'] = 'Feature flags';
$string['settings_flags_desc'] = 'All disabled for production by default. Live calls require both Enable and Live calls flags.';
$string['settings_endpoints'] = 'Endpoints';
$string['settings_jwt'] = 'JWT / security';
$string['settings_jwt_desc'] = 'HS256 is the current legacy assumption from Code.php; document any change from provider. Never commit secrets.';
$string['settings_api_optional'] = 'Optional API login (unused in legacy flow)';
$string['settings_api_optional_desc'] = 'Left empty. Legacy uses secret key + account name only.';

$string['proctopus_enabled'] = 'Enable Proctopus plugin features';
$string['proctopus_enabled_desc'] = 'Default OFF. Required for quizaccess rule enforcement and launch pages.';
$string['proctopus_legacy_mode'] = 'Legacy Proctopus mode (GET ?token=JWT)';
$string['proctopus_legacy_mode_desc'] = 'Default ON for Farabi. Uses /integration/simple/{account}/start/?token=… Only this legacy adapter uses query tokens.';
$string['proctopus_live_calls_enabled'] = 'Allow live HTTP to Proctopus';
$string['proctopus_live_calls_enabled_desc'] = 'Default OFF. Even when plugin is enabled, no real network calls until this is on (separate APPROVE + rotated secret).';

$string['proctopus_baseurl'] = 'Proctopus base URL';
$string['proctopus_baseurl_desc'] = 'Default https://farabi-proctoring.kaznu.kz (HTTPS only; host allowlisted in code).';
$string['proctopus_account_name'] = 'Account / tenant name';
$string['proctopus_account_name_desc'] = 'farabi_business only. Never kaznu_moodle.';
$string['proctopus_start_path'] = 'Legacy start path';
$string['proctopus_start_path_desc'] = 'Default /integration/simple/farabi_business/start/';
$string['proctopus_callback_url'] = 'Public callback URL (advertised)';
$string['proctopus_callback_url_desc'] = 'https://farabischool.kz/proctoring/proctopus/result — rewrite docs only until production APPROVE.';
$string['proctopus_jwt_secret'] = 'JWT secret';
$string['proctopus_jwt_secret_desc'] = 'Empty by default. Prefer env PROCTOPUS_JWT_SECRET. Chat-shared secrets are compromised — rotate before production. Never log.';
$string['proctopus_jwt_algorithm'] = 'JWT algorithm';
$string['proctopus_jwt_algorithm_desc'] = 'Legacy assumption: HS256 (Firebase JWT without alg in Code.php). Explicit encode only.';
$string['proctopus_token_ttl'] = 'Token / state TTL (seconds)';
$string['proctopus_clock_skew'] = 'Clock skew (seconds)';
$string['proctopus_issuer'] = 'JWT issuer (iss)';
$string['proctopus_default_duration'] = 'Default exam duration (minutes)';
$string['proctopus_default_duration_desc'] = 'Used when quiz timelimit and rule override are empty. Never hardcode 90.';
$string['proctopus_connect_timeout'] = 'Connect timeout (seconds)';
$string['proctopus_timeout'] = 'Total timeout (seconds)';
$string['proctopus_default_language'] = 'Default language';
$string['proctopus_default_mode'] = 'Default proctoring mode';
$string['proctopus_debug'] = 'Debug mode';
$string['proctopus_debug_desc'] = 'Default off. Never logs secrets or JWT.';
$string['proctopus_retention_days'] = 'Session retention (days)';
$string['proctopus_retention_days_desc'] = 'Raw callback bodies are never stored.';
$string['proctopus_api_login'] = 'API login (optional, unused)';
$string['proctopus_api_login_desc'] = 'Not used in legacy flow.';
$string['proctopus_api_password'] = 'API password (optional, unused)';
$string['proctopus_api_password_desc'] = 'Not used in legacy flow.';

$string['proctopus_not_configured'] = 'Proctoring is not available. Contact the course team.';
$string['proctopus_disabled'] = 'Proctoring is temporarily unavailable.';
$string['proctopus_start_failed'] = 'Could not connect to the proctoring system. Check your internet connection and try again.';
$string['proctopus_invalid_return'] = 'Could not confirm proctoring. Open the quiz again.';
$string['proctopus_callback_disabled'] = 'Proctoring callback is not configured.';

// Student-facing CTA / flow (no JWT / session / provider jargon).
$string['prep_title'] = 'Test preparation';
$string['prep_subtitle'] = 'Before you start, we will check your equipment and connection.';
$string['prep_back_quiz'] = 'Back to quiz';
$string['prep_ready_banner'] = 'Equipment check passed. Continue to proctoring.';
$string['prep_need_banner'] = 'Some checks need your attention. Fix them and try again.';

$string['stepper_label'] = 'Test stages';
$string['step_prep'] = 'Check';
$string['step_proctoring'] = 'Proctoring';
$string['step_test'] = 'Test';
$string['step_done'] = 'Finish';

$string['check_card_camera'] = 'Camera';
$string['check_card_camera_hint'] = 'Checking camera access';
$string['check_card_mic'] = 'Microphone';
$string['check_card_mic_hint'] = 'Checking microphone access';
$string['check_card_screen'] = 'Screen';
$string['check_card_screen_hint'] = 'Checking screen share support';
$string['check_card_connection'] = 'Connection';
$string['check_card_connection_hint'] = 'Checking connection to proctoring';
$string['check_card_extension'] = 'Proctoring';
$string['check_card_extension_hint'] = 'Farabi Proctoring extension will be verified next';

$string['check_status_pending'] = 'Waiting';
$string['check_status_checking'] = 'Checking…';
$string['check_status_success'] = 'Ready';
$string['check_status_warning'] = 'Action needed';
$string['check_status_error'] = 'Could not check';

$string['check_camera_ok'] = 'Camera is available in the browser';
$string['check_camera_need'] = 'Allow camera access in the browser.';
$string['check_camera_fail'] = 'Could not access the camera.';
$string['check_mic_ok'] = 'Microphone is available in the browser';
$string['check_mic_need'] = 'Allow microphone access in the browser.';
$string['check_mic_fail'] = 'Could not access the microphone.';
$string['check_screen_ok'] = 'Screen share is supported';
$string['check_screen_warn'] = 'Screen share may not be available in this browser';
$string['check_conn_ok'] = 'Connection is ok';
$string['check_conn_fail'] = 'Check your internet connection';
$string['check_ext_pending'] = 'Will be checked in proctoring';
$string['check_ext_note'] = 'The full check runs in Farabi Proctoring after you continue';

$string['cta_check_equipment'] = 'Check equipment';
$string['cta_go_proctoring'] = 'Continue to proctoring';
$string['cta_start_test'] = 'Start test';
$string['cta_open_prep'] = 'Prepare for the test';
$string['cta_retry_check'] = 'Retry check';
$string['cta_retry_extension'] = 'Check extension';
$string['cta_finish_test'] = 'Finish test';

$string['err_provider'] = 'Could not connect to the proctoring system. Check your internet connection and try again.';
$string['err_extension'] = 'The Farabi Proctoring extension is required to take this test.';
$string['err_https'] = 'A secure connection (HTTPS) is required.';
$string['err_browser'] = 'This browser does not support the required media features.';
$string['err_camera'] = 'Could not access the camera.';
$string['err_mic'] = 'Could not access the microphone.';

$string['launch_title'] = 'Proctoring';
$string['launch_intro'] = 'Complete preparation, then continue to proctoring.';
$string['launch_cta'] = 'Continue to proctoring';
$string['launch_cta_full'] = 'Prepare for the test';
$string['launch_continue_hint'] = 'Press the button below to continue.';
$string['launch_stub_mode'] = 'Practice mode: live remote observation is not used.';

$string['require_proctoring_title'] = 'This test requires proctoring';
$string['require_proctoring_body'] = 'Open preparation to check your equipment, then continue to proctoring. Do not close this browser window during the test.';
$string['status_unavailable'] = 'Proctoring unavailable';
$string['status_stub_mode'] = 'Practice mode';
$string['status_live_active'] = 'Proctoring active';
$string['status_required'] = 'Proctoring required';
$string['status_stub_notice_student'] = 'Practice mode — remote observation is not performed.';
$string['status_stub_notice_admin'] = 'Admin: live calls off. Camera/mic and remote invigilation may be limited.';
$string['check_integration'] = 'Local preparation';
$string['check_camera_stub'] = 'Camera — not used in practice mode';
$string['check_mic_stub'] = 'Microphone — not used in practice mode';
$string['check_live_skipped'] = 'Live proctoring connection — skipped';
$string['check_connection'] = 'Connection';
$string['check_camera'] = 'Camera';
$string['check_mic'] = 'Microphone';
$string['check_screen'] = 'Screen';

$string['event_session_created'] = 'Proctoring session created';
$string['event_session_started'] = 'Proctoring session started';
$string['event_callback_received'] = 'Proctoring callback received';
$string['event_session_completed'] = 'Proctoring session completed';
$string['event_session_failed'] = 'Proctoring session failed';
$string['event_proctoring_exam_completed'] = 'Proctopus exam completed (local session closed)';
$string['event_proctoring_result_received'] = 'Proctopus provider result received';

$string['task_reconcile_sessions'] = 'Reconcile stale Proctopus sessions';

$string['complete_title'] = 'Test finished';
$string['complete_heading'] = 'Test finished';
$string['complete_answers_sent'] = 'Answers submitted';
$string['complete_attempt_saved'] = 'Attempt saved';
$string['complete_proctoring_ended'] = 'Proctoring finished';
$string['complete_pending_provider'] = 'Proctoring data is being processed';
$string['complete_grade'] = 'Result: {$a->grade} / {$a->max}';
$string['complete_review'] = 'View review';
$string['complete_back_course'] = 'Back to course';
$string['complete_back_dashboard'] = 'My dashboard';

$string['proctopus:configure'] = 'Configure Proctopus';
$string['proctopus:viewstatus'] = 'View proctoring status';
$string['proctopus:viewreports'] = 'View proctoring reports';
$string['proctopus:retry'] = 'Retry proctoring session';
$string['proctopus:managecallbacks'] = 'Manage proctoring callbacks';
$string['privacy:metadata'] = 'Stores proctoring session status; no raw JWT or full callback body.';
