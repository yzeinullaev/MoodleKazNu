<?php
defined('MOODLE_INTERNAL') || die();

/** Allowed hosts for legacy Proctopus HTTP (no wildcards). */
function local_proctopus_allowed_hosts(): array {
    return ['farabi-proctoring.kaznu.kz'];
}

function local_proctopus_is_enabled(): bool {
    return (int) get_config('local_proctopus', 'proctopus_enabled') === 1;
}

/** Legacy GET ?token= transport mode (default ON for Farabi). */
function local_proctopus_legacy_mode(): bool {
    $v = get_config('local_proctopus', 'proctopus_legacy_mode');
    if ($v === false || $v === null || $v === '') {
        return true;
    }
    return (int) $v === 1;
}

/**
 * Real outbound calls. Default OFF.
 * Requires proctopus_enabled AND this flag.
 */
function local_proctopus_live_calls_enabled(): bool {
    return local_proctopus_is_enabled()
        && (int) get_config('local_proctopus', 'proctopus_live_calls_enabled') === 1;
}

/**
 * Live outbound start allowlist (cmids).
 * Empty or "*" = all course modules (when live_calls_enabled).
 * Comma/space-separated list otherwise (e.g. "123,151,191").
 */
function local_proctopus_live_cmid_allowed(int $cmid): bool {
    $raw = trim((string) (get_config('local_proctopus', 'proctopus_live_cmid_allowlist') ?? ''));
    if ($raw === '' || $raw === '*') {
        // No hard allowlist — any quiz with the access rule may go live.
        return true;
    }
    $allowed = array_filter(array_map('intval', preg_split('/[\s,;]+/', $raw)));
    if (!$allowed) {
        return true;
    }
    return in_array((int) $cmid, $allowed, true);
}

function local_proctopus_get_jwt_secret(): string {
    $env = getenv('PROCTOPUS_JWT_SECRET');
    if (is_string($env) && $env !== '') {
        return $env;
    }
    return (string) (get_config('local_proctopus', 'proctopus_jwt_secret') ?? '');
}

function local_proctopus_account_name(): string {
    $n = trim((string) (get_config('local_proctopus', 'proctopus_account_name') ?? ''));
    return $n !== '' ? $n : 'farabi_business';
}

function local_proctopus_jwt_algorithm(): string {
    $a = trim((string) (get_config('local_proctopus', 'proctopus_jwt_algorithm') ?? ''));
    // Legacy assumption documented as HS256; empty treated as HS256 for encode path when secret set.
    return $a !== '' ? $a : 'HS256';
}

function local_proctopus_baseurl(): string {
    $b = trim((string) (get_config('local_proctopus', 'proctopus_baseurl') ?? ''));
    return $b !== '' ? rtrim($b, '/') : 'https://farabi-proctoring.kaznu.kz';
}

function local_proctopus_start_path(): string {
    $p = trim((string) (get_config('local_proctopus', 'proctopus_start_path') ?? ''));
    if ($p === '') {
        $p = '/integration/simple/farabi_business/start/';
    }
    if ($p[0] !== '/') {
        $p = '/' . $p;
    }
    return $p;
}

function local_proctopus_issuer(): string {
    $iss = trim((string) (get_config('local_proctopus', 'proctopus_issuer') ?? ''));
    return $iss !== '' ? $iss : 'farabischool.kz';
}

function local_proctopus_token_ttl(): int {
    $ttl = (int) (get_config('local_proctopus', 'proctopus_token_ttl')
        ?: get_config('local_proctopus', 'proctopus_jwt_ttl')
        ?: 300);
    return max(30, $ttl);
}

/**
 * Open-redirect safe local URL under wwwroot.
 */
function local_proctopus_local_url(string $path, array $params = []): \moodle_url {
    if (preg_match('#^https?://#i', $path)) {
        $path = '/local/proctopus/index.php';
    }
    $path = '/' . ltrim($path, '/');
    if (!str_starts_with($path, '/local/proctopus/') && !str_starts_with($path, '/mod/quiz/')) {
        $path = '/local/proctopus/index.php';
    }
    // Forbid legacy domains appearing in path by accident.
    if (str_contains($path, 'dl.kaznu') || str_contains($path, 'kaznu_moodle')) {
        $path = '/local/proctopus/index.php';
    }
    return new \moodle_url($path, $params);
}

/**
 * Whether redirect target host is allowlisted (Proctopus or own site).
 */
function local_proctopus_is_redirect_host_allowed(string $url): bool {
    global $CFG;
    $parts = parse_url($url);
    if (empty($parts['scheme']) || strtolower($parts['scheme']) !== 'https') {
        return false;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    if ($host === '') {
        return false;
    }
    $own = parse_url($CFG->wwwroot, PHP_URL_HOST);
    $own = $own ? strtolower((string) $own) : '';
    if ($own !== '' && $host === $own) {
        return true;
    }
    return in_array($host, local_proctopus_allowed_hosts(), true);
}

/**
 * Safe log line — never token/URL with query.
 */
function local_proctopus_safe_log(string $correlation, string $outcome, int $httpstatus = 0, int $durationms = 0): void {
    $msg = 'proctopus correlation=' . $correlation
        . ' outcome=' . $outcome
        . ' http=' . $httpstatus
        . ' ms=' . $durationms;
    debugging($msg, DEBUG_DEVELOPER);
}

/**
 * Material for HMAC state (never raw JWT stored).
 */
function local_proctopus_state_key(): string {
    global $CFG;
    $secret = local_proctopus_get_jwt_secret();
    if ($secret === '') {
        // Deterministic site-local key for offline stub only when secret empty.
        return 'stub-state-' . ($CFG->siteidentifier ?? 'farabi');
    }
    return $secret;
}

/**
 * Integration mode for UI honesty (no technical secrets).
 *
 * @return string disabled|stub|live
 */
function local_proctopus_ui_mode(): string {
    if (!local_proctopus_is_enabled()) {
        return 'disabled';
    }
    if (local_proctopus_live_calls_enabled()) {
        return 'live';
    }
    return 'stub';
}

/**
 * Compact status chip for students (never JWT / session IDs / API URLs / stub-live jargon).
 *
 * @param string $mode disabled|stub|live (admin/mode only; students never see those words)
 * @param string $phase prep|admitted|unavailable|active
 */
function local_proctopus_render_status_chip(string $mode, string $phase = 'admitted'): string {
    if ($phase === 'unavailable' || $mode === 'disabled') {
        $cls = 'is-disabled';
        $text = get_string('status_unavailable', 'local_proctopus');
        $icon = 'fa-ban';
    } else if ($phase === 'prep') {
        $cls = 'is-prep';
        $text = get_string('status_required', 'local_proctopus');
        $icon = 'fa-shield';
    } else {
        // During attempt only.
        $cls = 'is-active';
        $text = get_string('status_live_active', 'local_proctopus');
        $icon = 'fa-shield';
    }
    return html_writer::div(
        html_writer::tag('i', '', ['class' => 'icon fa ' . $icon . ' fa-fw kzn-proctor-chip-icon', 'aria-hidden' => 'true'])
            . ' ' . html_writer::span(s($text), 'kzn-proctor-chip-text'),
        'kzn-proctor-chip ' . $cls,
        ['role' => 'status', 'aria-label' => $text]
    );
}

/**
 * Stepper: prep | proctoring | test | done
 */
function local_proctopus_render_stepper(string $current): string {
    $steps = [
        'prep' => get_string('step_prep', 'local_proctopus'),
        'proctoring' => get_string('step_proctoring', 'local_proctopus'),
        'test' => get_string('step_test', 'local_proctopus'),
        'done' => get_string('step_done', 'local_proctopus'),
    ];
    $order = array_keys($steps);
    $curidx = array_search($current, $order, true);
    if ($curidx === false) {
        $curidx = 0;
    }

    $html = html_writer::start_tag('nav', [
        'class' => 'fps-stepper',
        'aria-label' => get_string('stepper_label', 'local_proctopus'),
    ]);
    $html .= html_writer::start_tag('ol', ['class' => 'fps-stepper-list']);
    foreach ($order as $i => $key) {
        if ($i < $curidx) {
            $state = 'done';
            $mark = '✓';
        } else if ($i === $curidx) {
            $state = 'current';
            $mark = '●';
        } else {
            $state = 'todo';
            $mark = '○';
        }
        $attrs = ['class' => 'fps-step is-' . $state];
        if ($state === 'current') {
            $attrs['aria-current'] = 'step';
        }
        $html .= html_writer::tag('li',
            html_writer::span($mark, 'fps-step-mark', ['aria-hidden' => 'true'])
                . ' ' . html_writer::span(s($steps[$key]), 'fps-step-label'),
            $attrs
        );
    }
    $html .= html_writer::end_tag('ol');
    $html .= html_writer::end_tag('nav');
    return $html;
}

/**
 * Quiz landing CTA — opens preparation page (never launches Proctopus directly).
 */
function local_proctopus_render_readiness_panel(int $cmid, bool $forlaunch = false): string {
    $mode = local_proctopus_ui_mode();
    if ($mode === 'disabled') {
        return html_writer::div(
            html_writer::tag('p', get_string('status_unavailable', 'local_proctopus')),
            'kzn-proctor-panel is-disabled fps-gate-panel',
            ['data-kzn-proctor' => '1']
        );
    }

    $check = new moodle_url('/local/proctopus/check.php', ['cmid' => $cmid]);
    $title = get_string('require_proctoring_title', 'local_proctopus');
    $intro = get_string('require_proctoring_body', 'local_proctopus');
    $cta = get_string('cta_open_prep', 'local_proctopus');

    $html = '<section class="kzn-proctor-panel fps-gate-panel" data-kzn-proctor="1" aria-labelledby="kzn-proctor-title">'
        . '<h3 id="kzn-proctor-title">' . s($title) . '</h3>'
        . '<p class="kzn-proctor-intro">' . s($intro) . '</p>'
        . html_writer::link($check, html_writer::tag('i', '', [
                'class' => 'icon fa fa-shield fa-fw',
                'aria-hidden' => 'true',
            ]) . ' ' . s($cta), [
            'class' => 'btn btn-primary kzn-proctor-cta fps-cta',
        ])
        . '</section>';
    return $html;
}

/**
 * True when this user has an in-progress quiz attempt for the given CM.
 */
function local_proctopus_user_has_inprogress_attempt(int $userid, int $cmid): bool {
    global $DB;
    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false);
    if (!$cm) {
        return false;
    }
    return $DB->record_exists('quiz_attempts', [
        'quiz' => (int) $cm->instance,
        'userid' => $userid,
        'state' => 'inprogress',
    ]);
}

/**
 * during attempt / summary indicator — only while attempt is in progress and session is open/admitted.
 */
function local_proctopus_maybe_render_attempt_indicator(int $cmid, int $userid): string {
    if (!local_proctopus_is_enabled()) {
        return '';
    }
    if (!local_proctopus_user_has_inprogress_attempt($userid, $cmid)) {
        return '';
    }
    if (!class_exists(\local_proctopus\service\session_service::class)) {
        return '';
    }
    $svc = new \local_proctopus\service\session_service();
    if (!$svc->user_has_admitted_session($userid, $cmid)) {
        return '';
    }
    return local_proctopus_render_status_chip(local_proctopus_ui_mode(), 'active');
}

/**
 * Early CSS load (safe if output already started for other plugins).
 */
function local_proctopus_before_standard_head_html() {
    global $CFG;
    $rev = get_config('local_proctopus', 'version') ?: '1';
    $href = $CFG->wwwroot . '/local/proctopus/styles.css?rev=' . rawurlencode((string) $rev);
    echo '<link rel="stylesheet" type="text/css" href="' . s($href) . '" />' . "\n";
}

function local_proctopus_before_standard_html_head() {
    // Styles loaded via before_standard_head_html (echo) to avoid requires race.
}

function local_proctopus_before_footer() {
    global $PAGE, $USER, $DB;

    if (!isloggedin() || isguestuser()) {
        return;
    }
    if (!local_proctopus_is_enabled()) {
        return;
    }

    $type = (string) ($PAGE->pagetype ?? '');
    // Review / complete / results = normal UI. Bar only on live attempt chrome.
    $focus = (strpos($type, 'mod-quiz-attempt') === 0
        || strpos($type, 'mod-quiz-summary') === 0
        || strpos($type, 'mod-quiz-processattempt') === 0);

    if (!$focus || strpos($type, 'mod-quiz-review') === 0) {
        return;
    }

    $cmid = 0;
    if (!empty($PAGE->cm) && !empty($PAGE->cm->id)) {
        $cmid = (int) $PAGE->cm->id;
    } else if (!empty($PAGE->context) && $PAGE->context->contextlevel == CONTEXT_MODULE) {
        $cmid = (int) $PAGE->context->instanceid;
    }
    if ($cmid < 1) {
        $cmid = optional_param('cmid', 0, PARAM_INT);
    }
    if ($cmid < 1) {
        return;
    }

    $cm = get_coursemodule_from_id('quiz', $cmid, 0, false);
    if (!$cm) {
        return;
    }
    if (!$DB->record_exists('quizaccess_proctopus', ['quizid' => (int) $cm->instance, 'proctopusrequired' => 1])) {
        return;
    }

    // Only when attempt is actually in progress + admitted session.
    $chip = local_proctopus_maybe_render_attempt_indicator($cmid, (int) $USER->id);
    if ($chip === '') {
        return;
    }
    echo html_writer::div($chip, 'kzn-proctor-attempt-bar');
}
