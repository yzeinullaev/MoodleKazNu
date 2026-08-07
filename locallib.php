<?php
defined('MOODLE_INTERNAL') || die();

/** Demo / featured paid course shortname. */
const LOCAL_KAZNU_COURSE_SHORTNAME = 'SUMMER2026';

/** XP required per level (hard progression). */
const LOCAL_KAZNU_XP_PER_LEVEL = 200;

/** XP awards. */
const LOCAL_KAZNU_XP_QUIZ_PASS = 120;
const LOCAL_KAZNU_XP_QUIZ_PERFECT = 80;
const LOCAL_KAZNU_XP_MODULE = 40;
const LOCAL_KAZNU_XP_ENROL = 50;

/** Max display level (Farabi Master). */
const LOCAL_KAZNU_MAX_LEVEL = 10;

/**
 * Demo / featured summer course or null.
 * Note: summer school is retired for students — kept for admin/setup only.
 */
function local_kaznu_get_demo_course(): ?stdClass {
    global $DB;
    return $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]) ?: null;
}

/**
 * Whether course is retired / hidden from the student catalogue.
 */
function local_kaznu_is_retired_course(stdClass $course): bool {
    return strtoupper((string) $course->shortname) === LOCAL_KAZNU_COURSE_SHORTNAME;
}

/**
 * All catalogue courses (site courses except frontpage).
 *
 * @return stdClass[]
 */
function local_kaznu_get_catalogue_courses(bool $includehidden = true, bool $includeretired = false): array {
    global $DB;

    $params = [];
    $sql = "SELECT * FROM {course} WHERE id > 1";
    if (!$includehidden) {
        $sql .= " AND visible = 1";
    }
    if (!$includeretired) {
        $sql .= " AND shortname <> :retired";
        $params['retired'] = LOCAL_KAZNU_COURSE_SHORTNAME;
    }
    $sql .= " ORDER BY sortorder ASC, fullname ASC";
    return array_values($DB->get_records_sql($sql, $params));
}

/**
 * Shelf key for MasterClass-style rows.
 */
function local_kaznu_course_shelf(stdClass $course): string {
    $sn = strtoupper($course->shortname);
    $name = $course->fullname;
    if ($sn === 'SUMMER2026' || stripos($name, 'летн') !== false || stripos($name, 'summer') !== false) {
        return 'summer';
    }
    if (stripos($sn, 'DBA') !== false || stripos($name, 'DBA') !== false || in_array($sn, ['BRM', 'RM'], true)) {
        return 'research';
    }
    if (stripos($sn, 'EMBA') !== false || stripos($name, 'EMBA') !== false) {
        return 'business';
    }
    if (stripos($name, 'research') !== false) {
        return 'research';
    }
    return 'business';
}

/**
 * Accent gradient index for course tile (no uploaded images yet).
 */
function local_kaznu_course_accent(stdClass $course): int {
    return ((int) $course->id % 5) + 1;
}

/**
 * First YouTube URL activity in a course, if any.
 */
function local_kaznu_find_course_youtube_url(int $courseid): string {
    global $DB;

    if ($courseid <= 1) {
        return '';
    }

    $sql = "SELECT u.externalurl
              FROM {url} u
              JOIN {course_modules} cm ON cm.instance = u.id AND cm.course = u.course
              JOIN {modules} m ON m.id = cm.module AND m.name = 'url'
             WHERE u.course = :courseid
               AND cm.deletioninprogress = 0
               AND (u.externalurl LIKE :yt1 OR u.externalurl LIKE :yt2)
          ORDER BY cm.section ASC, cm.id ASC";
    $url = $DB->get_field_sql($sql, [
        'courseid' => $courseid,
        'yt1' => '%youtube.com%',
        'yt2' => '%youtu.be%',
    ]);
    return is_string($url) ? $url : '';
}

/**
 * Demo trailer YouTube IDs (rotated per course).
 *
 * @return string[]
 */
function local_kaznu_mock_trailer_ids(): array {
    return [
        'qp0HIF3SfI4', // TED: How great leaders inspire action
        'UF8uR6Z6KLc', // Steve Jobs Stanford
        'arj7oStGLkU', // TED: Inside the mind of a master procrastinator
        'i9KEU97utPw',
        '8CwoCtBZn9k',
        '3QIfkeA6HBY',
        'RLal0oX3s6s',
        'ZXsQAXx_ao0',
    ];
}

/**
 * Resolve course trailer YouTube watch/embed URL (real activity or mock).
 */
function local_kaznu_course_trailer_url(stdClass $course): string {
    $found = local_kaznu_find_course_youtube_url((int) $course->id);
    if ($found !== '') {
        return $found;
    }
    $ids = local_kaznu_mock_trailer_ids();
    $id = $ids[((int) $course->id) % count($ids)];
    return 'https://www.youtube.com/embed/' . $id . '?rel=0&modestbranding=1';
}

/**
 * Hero trailer embed HTML for course landing.
 */
function local_kaznu_render_course_trailer(stdClass $course): string {
    $embed = local_kaznu_youtube_embed_from_url(local_kaznu_course_trailer_url($course));
    if ($embed === '') {
        return '';
    }
    // Prefer accessible title for course trailer.
    $embed = str_replace('title="YouTube"', 'title="' . s(get_string('hub_trailer', 'local_kaznu')) . '"', $embed);
    return '<div class="kzn-class-trailer" data-kaznu-trailer="1">' . $embed . '</div>';
}

/**
 * Whether user is enrolled in a course.
 */
function local_kaznu_user_enrolled_in(int $userid, int $courseid): bool {
    $context = context_course::instance($courseid);
    return is_enrolled($context, $userid, '', true);
}

/**
 * Payment confirmation token (demo).
 */
function local_kaznu_payment_token(): string {
    $token = get_config('local_kaznu', 'paymenttoken');
    return $token ?: 'DEMO-KZN-2026';
}

/**
 * Whether the user is enrolled in the demo course.
 */
function local_kaznu_is_enrolled(int $userid): bool {
    $course = local_kaznu_get_demo_course();
    if (!$course) {
        return false;
    }
    return local_kaznu_user_enrolled_in($userid, (int) $course->id);
}

/**
 * Enrol user into a course (manual enrolment).
 */
function local_kaznu_enrol_user(stdClass $course, int $userid, string $roleshortname = 'student'): bool {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $plugin = enrol_get_plugin('manual');
    $instance = null;

    foreach (enrol_get_instances($course->id, true) as $inst) {
        if ($inst->enrol === 'manual') {
            $instance = $inst;
            break;
        }
    }

    if (!$instance) {
        $id = $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', ['id' => $id], '*', MUST_EXIST);
    }

    if ($DB->record_exists('user_enrolments', ['userid' => $userid, 'enrolid' => $instance->id])) {
        return false;
    }

    $plugin->enrol_user($instance, $userid, $role->id);
    local_kaznu_award_xp($userid, LOCAL_KAZNU_XP_ENROL, 'enrol', 'first_steps');
    return true;
}

/**
 * Level title from level number.
 */
function local_kaznu_level_title(int $level): string {
    if ($level >= 10) {
        return get_string('xp_rank_master', 'local_kaznu');
    }
    if ($level >= 7) {
        return get_string('xp_rank_strategist', 'local_kaznu');
    }
    if ($level >= 5) {
        return get_string('xp_rank_analyst', 'local_kaznu');
    }
    if ($level >= 3) {
        return get_string('xp_rank_scholar', 'local_kaznu');
    }
    return get_string('xp_rank_novice', 'local_kaznu');
}

/**
 * Get or create XP row for user.
 */
function local_kaznu_get_xp(int $userid): stdClass {
    global $DB;

    $rec = $DB->get_record('local_kaznu_xp', ['userid' => $userid]);
    if ($rec) {
        return $rec;
    }

    $rec = (object) [
        'userid' => $userid,
        'xp' => 0,
        'level' => 1,
        'badges' => json_encode([]),
        'timemodified' => time(),
    ];
    $rec->id = $DB->insert_record('local_kaznu_xp', $rec);
    return $rec;
}

/**
 * Award XP and optional badge. Returns ['xp'=>int,'levelup'=>bool,'level'=>int,'gained'=>int].
 *
 * @return array{xp:int,level:int,levelup:bool,gained:int,title:string}
 */
function local_kaznu_award_xp(int $userid, int $amount, string $reason = '', string $badge = ''): array {
    global $DB;

    if ($userid <= 0 || $amount <= 0) {
        return ['xp' => 0, 'level' => 1, 'levelup' => false, 'gained' => 0, 'title' => ''];
    }

    // Table may not exist until upgrade.
    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_kaznu_xp')) {
        return ['xp' => 0, 'level' => 1, 'levelup' => false, 'gained' => 0, 'title' => ''];
    }

    $rec = local_kaznu_get_xp($userid);
    $oldlevel = (int) $rec->level;
    $rec->xp = (int) $rec->xp + $amount;
    $rec->level = max(1, (int) floor($rec->xp / LOCAL_KAZNU_XP_PER_LEVEL) + 1);
    $rec->timemodified = time();

    $badges = json_decode($rec->badges ?: '[]', true);
    if (!is_array($badges)) {
        $badges = [];
    }
    if ($badge !== '' && !in_array($badge, $badges, true)) {
        $badges[] = $badge;
        $rec->badges = json_encode(array_values($badges));
    } else {
        $rec->badges = json_encode(array_values($badges));
    }

    $DB->update_record('local_kaznu_xp', $rec);

    $levelup = $rec->level > $oldlevel;
    // Always show XP toast after any award (tests, enrol, etc.).
    if (isset($GLOBALS['SESSION'])) {
        $GLOBALS['SESSION']->local_kaznu_celebrate = [
            'gained' => $amount,
            'xp' => (int) $rec->xp,
            'level' => (int) $rec->level,
            'levelup' => $levelup,
            'reason' => $reason,
            'title' => local_kaznu_level_title((int) $rec->level),
        ];
    }

    return [
        'xp' => (int) $rec->xp,
        'level' => (int) $rec->level,
        'levelup' => $levelup,
        'gained' => $amount,
        'title' => local_kaznu_level_title((int) $rec->level),
    ];
}

/**
 * Progress toward next level (0–100).
 */
function local_kaznu_xp_progress(stdClass $rec): array {
    $xp = (int) $rec->xp;
    $into = $xp % LOCAL_KAZNU_XP_PER_LEVEL;
    $pct = (int) round(($into / LOCAL_KAZNU_XP_PER_LEVEL) * 100);
    return [
        'into' => $into,
        'need' => LOCAL_KAZNU_XP_PER_LEVEL,
        'pct' => $pct,
        'title' => local_kaznu_level_title((int) $rec->level),
    ];
}

/**
 * Quiz pages that must not show achievements / XP HUD UI (active attempt focus).
 * Uses Moodle pagetype prefixes — not raw $_SERVER URL alone.
 */
function local_kaznu_is_quiz_focus_page(): bool {
    global $PAGE;

    $type = (string) ($PAGE->pagetype ?? '');
    // Proctopus preparation / launch: hide achievements and keep exam-like focus.
    // Completion page is normal again (trophy allowed).
    if (strpos($type, 'local-proctopus-check') === 0
            || strpos($type, 'local-proctopus-launch') === 0) {
        return true;
    }
    if (!empty($PAGE->bodyclasses) && is_string($PAGE->bodyclasses)
            && str_contains($PAGE->bodyclasses, 'local-proctopus-hide-achievements')) {
        return true;
    }
    // Also match by URL path when body classes not yet final.
    if (!empty($PAGE->url)) {
        $path = $PAGE->url->get_path();
        if (is_string($path)) {
            if (str_contains($path, '/local/proctopus/check.php')
                    || str_contains($path, '/local/proctopus/launch.php')) {
                return true;
            }
            // return.php is mid-proctoring handoff.
            if (str_contains($path, '/local/proctopus/return.php')) {
                return true;
            }
        }
    }

    if ($type === '') {
        return false;
    }
    // Active attempt chrome only (not review — results may keep light portal shell).
    $prefixes = [
        'mod-quiz-attempt',
        'mod-quiz-summary',
        'mod-quiz-processattempt',
    ];
    foreach ($prefixes as $prefix) {
        if (strpos($type, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Compact collapsible achievements panel (default collapsed; state in localStorage).
 *
 * Collapsed = trophy FAB only (no Lv/XP text). Expanded = panel with details.
 * Does not change XP awarding. Callers must not output this on quiz focus pages.
 *
 * @param array{
 *   id?:string,
 *   title:string,
 *   level:int,
 *   xp:int,
 *   bodyhtml:string,
 *   variant?:string,
 *   extra_class?:string
 * } $data
 */
function local_kaznu_render_achievement_panel(array $data): string {
    global $OUTPUT;
    static $scriptprinted = false;

    $id = !empty($data['id']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', (string) $data['id']) : 'kzn-achievements';
    if ($id === '') {
        $id = 'kzn-achievements';
    }
    $title = (string) ($data['title'] ?? get_string('xp_quiz_panel_title', 'local_kaznu'));
    $level = (int) ($data['level'] ?? 1);
    $xp = (int) ($data['xp'] ?? 0);
    $bodyhtml = (string) ($data['bodyhtml'] ?? '');
    $variant = !empty($data['variant']) ? preg_replace('/[^a-z0-9_-]/', '', (string) $data['variant']) : 'hud';
    $extra = !empty($data['extra_class']) ? ' ' . preg_replace('/[^a-zA-Z0-9 _-]/', '', (string) $data['extra_class']) : '';

    $summary = get_string('achievements_level_xp', 'local_kaznu', (object) [
        'level' => $level,
        'xp' => $xp,
    ]);
    $bodyid = $id . '-body';
    $btnid = $id . '-btn';
    $closeid = $id . '-close';
    $label = get_string('achievements_panel_label', 'local_kaznu');

    // Moodle Font Awesome trophy (no emoji).
    $icon = '';
    if (!empty($OUTPUT) && is_object($OUTPUT) && method_exists($OUTPUT, 'pix_icon')) {
        try {
            $icon = $OUTPUT->pix_icon('i/badge', '', 'core', [
                'class' => 'kzn-achievement-icon-img',
                'aria-hidden' => 'true',
            ]);
        } catch (\Throwable $e) {
            $icon = '';
        }
    }
    if ($icon === '') {
        $icon = '<i class="icon fa fa-trophy fa-fw kzn-achievement-icon-fa" aria-hidden="true"></i>';
    }

    $html = '<aside class="kzn-achievement-panel is-collapsed kzn-achievement--' . s($variant) . $extra . '"'
        . ' data-kzn-achievements="1" data-kzn-ach-id="' . s($id) . '" id="' . s($id) . '">'
        . '<button type="button" class="kzn-achievement-toggle" id="' . s($btnid) . '"'
        . ' aria-expanded="false" aria-controls="' . s($bodyid) . '"'
        . ' aria-label="' . s($label) . '"'
        . ' title="' . s($label) . '">'
        . '<span class="kzn-achievement-icon">' . $icon . '</span>'
        . '</button>'
        . '<div class="kzn-achievement-body" id="' . s($bodyid) . '" role="region"'
        . ' aria-label="' . s($title) . '" hidden>'
        . '<div class="kzn-achievement-head">'
        . '<span class="kzn-achievement-title">' . s($title) . '</span>'
        . '<button type="button" class="kzn-achievement-close" id="' . s($closeid) . '"'
        . ' aria-label="' . s(get_string('achievements_close', 'local_kaznu')) . '">'
        . '<span aria-hidden="true">&times;</span>'
        . '</button>'
        . '</div>'
        . '<p class="kzn-achievement-summary">' . s($summary) . '</p>'
        . $bodyhtml
        . '</div>'
        . '</aside>';

    if (!$scriptprinted) {
        $scriptprinted = true;
        $html .= local_kaznu_achievement_panel_js();
    }
    return $html;
}

/**
 * One shared JS for all achievement panels (safe if localStorage unavailable).
 * KEY farabi_achievements_open: 1=expanded, 0=collapsed. Default collapsed.
 */
function local_kaznu_achievement_panel_js(): string {
    return '<script>(function(){'
        . 'var KEY="farabi_achievements_open";'
        . 'var KEY_LEGACY="farabi_achievements_collapsed";'
        . 'function openDefault(){'
        . 'try{'
        . 'var v=localStorage.getItem(KEY);'
        . 'if(v==="1")return true;'
        . 'if(v==="0")return false;'
        // Migrate old inverted key once (collapsed=1 → open=0).
        . 'var leg=localStorage.getItem(KEY_LEGACY);'
        . 'if(leg==="0")return true;'
        . 'if(leg==="1")return false;'
        . '}catch(e){}'
        . 'return false;'
        . '}'
        . 'function apply(panel,open){'
        . 'var btn=panel.querySelector(".kzn-achievement-toggle");'
        . 'var body=panel.querySelector(".kzn-achievement-body");'
        . 'if(!btn||!body)return;'
        . 'panel.classList.toggle("is-collapsed",!open);'
        . 'panel.classList.toggle("is-expanded",open);'
        . 'btn.setAttribute("aria-expanded",open?"true":"false");'
        . 'if(open){body.removeAttribute("hidden");}else{body.setAttribute("hidden","");}'
        . '}'
        . 'function init(panel){'
        . 'if(panel.getAttribute("data-kzn-ach-bound")==="1")return;'
        . 'panel.setAttribute("data-kzn-ach-bound","1");'
        . 'var btn=panel.querySelector(".kzn-achievement-toggle");'
        . 'var close=panel.querySelector(".kzn-achievement-close");'
        . 'if(!btn)return;'
        . 'var open=openDefault();'
        . 'apply(panel,open);'
        . 'function setOpen(next){'
        . 'apply(panel,next);'
        . 'try{localStorage.setItem(KEY,next?"1":"0");}catch(e){}'
        . '}'
        . 'btn.addEventListener("click",function(){'
        . 'setOpen(!panel.classList.contains("is-expanded"));'
        . '});'
        . 'if(close){close.addEventListener("click",function(e){e.preventDefault();setOpen(false);btn.focus();});}'
        . '}'
        . 'function boot(){document.querySelectorAll("[data-kzn-achievements]").forEach(init);}'
        . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",boot);}else{boot();}'
        . '})();</script>';
}

/**
 * Demo leaderboard names (used when real XP rows are scarce).
 *
 * @return array<int,array{firstname:string,lastname:string,xp:int}>
 */
function local_kaznu_mock_leaderboard_seed(): array {
    return [
        ['firstname' => 'Айгерим', 'lastname' => 'Нурланова', 'xp' => 1240],
        ['firstname' => 'Данияр', 'lastname' => 'Сериков', 'xp' => 980],
        ['firstname' => 'Сабина', 'lastname' => 'Касымова', 'xp' => 760],
        ['firstname' => 'Ерлан', 'lastname' => 'Токтаров', 'xp' => 620],
        ['firstname' => 'Камила', 'lastname' => 'Жумабаева', 'xp' => 480],
        ['firstname' => 'Нурсултан', 'lastname' => 'Ибраев', 'xp' => 360],
        ['firstname' => 'Алина', 'lastname' => 'Мухамедова', 'xp' => 260],
        ['firstname' => 'Тимур', 'lastname' => 'Алиев', 'xp' => 140],
    ];
}

/**
 * Build a leaderboard row object (real or mock).
 */
function local_kaznu_leaderboard_row(string $firstname, string $lastname, int $xp, int $userid = 0): stdClass {
    $level = max(1, (int) floor($xp / LOCAL_KAZNU_XP_PER_LEVEL) + 1);
    return (object) [
        'userid' => $userid,
        'xp' => $xp,
        'level' => $level,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'firstnamephonetic' => '',
        'lastnamephonetic' => '',
        'middlename' => '',
        'alternatename' => '',
        'timemodified' => time(),
        'ismock' => $userid <= 0,
    ];
}

/**
 * Leaderboard top N (pads with demo names if the table is sparse).
 *
 * @return array<int,stdClass>
 */
function local_kaznu_leaderboard(int $limit = 10): array {
    global $DB;

    $rows = [];
    $dbman = $DB->get_manager();
    if ($dbman->table_exists('local_kaznu_xp')) {
        $sql = "SELECT x.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
                  FROM {local_kaznu_xp} x
                  JOIN {user} u ON u.id = x.userid
                 WHERE u.deleted = 0 AND u.suspended = 0 AND x.xp > 0
              ORDER BY x.xp DESC, x.timemodified ASC";
        foreach ($DB->get_records_sql($sql, [], 0, $limit) as $row) {
            $row->ismock = false;
            $rows[] = $row;
        }
    }

    if (count($rows) >= $limit) {
        return array_slice($rows, 0, $limit);
    }

    $seen = [];
    foreach ($rows as $row) {
        $seen[core_text::strtolower($row->firstname . '|' . $row->lastname)] = true;
    }

    $mockid = -1;
    foreach (local_kaznu_mock_leaderboard_seed() as $seed) {
        $key = core_text::strtolower($seed['firstname'] . '|' . $seed['lastname']);
        if (isset($seen[$key])) {
            continue;
        }
        $rows[] = local_kaznu_leaderboard_row($seed['firstname'], $seed['lastname'], $seed['xp'], $mockid--);
        $seen[$key] = true;
        if (count($rows) >= $limit) {
            break;
        }
    }

    usort($rows, static function ($a, $b) {
        if ((int) $a->xp !== (int) $b->xp) {
            return (int) $b->xp <=> (int) $a->xp;
        }
        return (int) ($a->timemodified ?? 0) <=> (int) ($b->timemodified ?? 0);
    });

    return array_slice(array_values($rows), 0, $limit);
}

/**
 * Award quiz XP once per quiz (idempotent). Pass if grade ≥ 60% of max.
 *
 * @return bool True if XP was awarded now.
 */
function local_kaznu_award_quiz_xp(int $userid, int $quizid, float $finalgrade, float $grademax = 100.0): bool {
    global $DB;

    if ($userid <= 0 || $quizid <= 0) {
        return false;
    }

    $max = $grademax > 0 ? $grademax : 100.0;
    $pct = ($finalgrade / $max) * 100.0;
    if ($pct < 60.0 && $finalgrade < 60.0) {
        return false;
    }

    $key = 'quiz:' . $quizid;
    $rec = local_kaznu_get_xp($userid);
    $badges = json_decode($rec->badges ?: '[]', true);
    if (!is_array($badges)) {
        $badges = [];
    }
    if (in_array($key, $badges, true)) {
        return false; // Already awarded for this quiz.
    }

    $xp = LOCAL_KAZNU_XP_QUIZ_PASS;
    $badge = 'quiz_pass';
    if ($pct >= 99.5 || $finalgrade >= $max) {
        $xp += LOCAL_KAZNU_XP_QUIZ_PERFECT;
        $badge = 'perfect_score';
    }

    local_kaznu_award_xp($userid, $xp, 'quiz_pass', $badge);

    // Persist per-quiz lock after award (award_xp may have rewritten badges).
    $rec = local_kaznu_get_xp($userid);
    $badges = json_decode($rec->badges ?: '[]', true);
    if (!is_array($badges)) {
        $badges = [];
    }
    if (!in_array($key, $badges, true)) {
        $badges[] = $key;
        $rec->badges = json_encode(array_values($badges));
        $DB->update_record('local_kaznu_xp', $rec);
    }

    return true;
}

/**
 * Mark quiz CM complete with pass grade for one user (>= 60%).
 */
function local_kaznu_sync_user_quiz_completion(stdClass $course, int $userid, int $quizid): bool {
    global $CFG, $DB;

    // Must import $CFG into function scope — completionlib.php reads $CFG->dirroot locally.
    require_once($CFG->libdir . '/completionlib.php');

    $gi = $DB->get_record('grade_items', [
        'courseid' => $course->id,
        'itemmodule' => 'quiz',
        'iteminstance' => $quizid,
    ]);
    if (!$gi) {
        return false;
    }

    $gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $userid]);
    if (!$gg || $gg->finalgrade === null) {
        return false;
    }

    $final = (float) $gg->finalgrade;
    $max = (float) ($gi->grademax ?: 100);
    $pct = $max > 0 ? ($final / $max) * 100.0 : $final;
    if ($pct < 60.0 && $final < 60.0) {
        return false;
    }

    // Always award XP on pass (even if completion tracking is off).
    local_kaznu_award_quiz_xp($userid, $quizid, $final, $max);

    $cm = get_coursemodule_from_instance('quiz', $quizid, $course->id);
    if (!$cm || (int) $cm->completion === COMPLETION_TRACKING_NONE) {
        return true;
    }

    $state = COMPLETION_COMPLETE_PASS;
    $now = time();
    $rec = $DB->get_record('course_modules_completion', [
        'coursemoduleid' => $cm->id,
        'userid' => $userid,
    ]);
    if ($rec) {
        if ((int) $rec->completionstate !== $state) {
            $rec->completionstate = $state;
            $rec->timemodified = $now;
            $DB->update_record('course_modules_completion', $rec);
            $completion = new completion_info($course);
            $completion->update_state($cm, $state, $userid, true);
            rebuild_course_cache($course->id, true);
        }
    } else {
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cm->id,
            'userid' => $userid,
            'completionstate' => $state,
            'timemodified' => $now,
        ]);
        $completion = new completion_info($course);
        $completion->update_state($cm, $state, $userid, true);
        rebuild_course_cache($course->id, true);
    }

    return true;
}

/**
 * Write pass-grade completion for all users on all course quizzes.
 */
function local_kaznu_sync_quiz_pass_completions(stdClass $course): void {
    global $DB;

    foreach ($DB->get_records('quiz', ['course' => $course->id]) as $quiz) {
        if (strpos($quiz->name, 'Тест модуля') === false && stripos($quiz->name, 'экзамен') === false) {
            continue;
        }
        $gi = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemmodule' => 'quiz',
            'iteminstance' => $quiz->id,
        ]);
        if (!$gi) {
            continue;
        }
        foreach ($DB->get_records('grade_grades', ['itemid' => $gi->id]) as $gg) {
            if ($gg->finalgrade !== null && (float) $gg->finalgrade >= 60) {
                local_kaznu_sync_user_quiz_completion($course, (int) $gg->userid, (int) $quiz->id);
            }
        }
    }
}

/**
 * Show grades and pass/fail after quiz attempt.
 */
function local_kaznu_apply_quiz_review_settings(int $quizid): void {
    global $DB;

    $reviewwhen = 4096 + 256 + 16; // IMMEDIATELY_AFTER | LATER_WHILE_OPEN | AFTER_CLOSE
    $DB->update_record('quiz', (object) [
        'id' => $quizid,
        'reviewattempt' => 65536 + $reviewwhen,
        'reviewmaxmarks' => $reviewwhen,
        'reviewmarks' => $reviewwhen,
        'reviewoverallfeedback' => $reviewwhen,
        'reviewcorrectness' => $reviewwhen,
        'reviewgeneralfeedback' => $reviewwhen,
        'reviewspecificfeedback' => $reviewwhen,
        'reviewrightanswer' => 0,
    ]);
    $DB->set_field('grade_items', 'gradepass', 60, [
        'itemmodule' => 'quiz',
        'iteminstance' => $quizid,
    ]);
}

/**
 * URL for payment confirmation (used in QR and pay link).
 *
 * @param int $courseid Course to enrol into (0 = demo summer course).
 */
function local_kaznu_confirm_url(int $courseid = 0): moodle_url {
    $params = [
        'action' => 'confirm',
        'token' => local_kaznu_payment_token(),
    ];
    if ($courseid > 1) {
        $params['id'] = $courseid;
    }
    return new moodle_url('/local/kaznu/pay.php', $params);
}

/**
 * Plain-text course blurb from Moodle summary (for class landing).
 */
function local_kaznu_course_blurb(stdClass $course, int $maxlen = 280): string {
    $raw = trim(html_to_text($course->summary ?? '', 0, false));
    $raw = preg_replace('/\s+/u', ' ', $raw) ?? '';
    if ($raw === '') {
        return get_string('hub_blurb_default', 'local_kaznu');
    }
    if (\core_text::strlen($raw) > $maxlen) {
        return \core_text::substr($raw, 0, $maxlen - 1) . '…';
    }
    return $raw;
}

/**
 * Short marketing tagline under the class title.
 */
function local_kaznu_course_tagline(stdClass $course): string {
    $shelf = local_kaznu_course_shelf($course);
    $key = 'hub_tagline_' . $shelf;
    if (get_string_manager()->string_exists($key, 'local_kaznu')) {
        return get_string($key, 'local_kaznu');
    }
    return get_string('hub_tagline_default', 'local_kaznu');
}

/**
 * Hide Moodle branding for students (keep for site admins).
 */
function local_kaznu_should_debrand(): bool {
    if (!isloggedin() || isguestuser()) {
        return false;
    }
    $sys = context_system::instance();
    if (has_capability('moodle/site:config', $sys)) {
        return false;
    }
    return local_kaznu_is_student_shell_page();
}

/**
 * Make catalogue courses visible for the demo shelf (never unhide retired summer school).
 */
function local_kaznu_ensure_catalogue_visible(): void {
    global $DB;
    $DB->execute(
        "UPDATE {course} SET visible = 1 WHERE id > 1 AND visible = 0 AND shortname <> ?",
        [LOCAL_KAZNU_COURSE_SHORTNAME]
    );
    // Keep summer school hidden from students.
    $DB->set_field('course', 'visible', 0, ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]);
}

/**
 * Suspend student enrolments on the retired summer course.
 */
function local_kaznu_retire_summer_course(): void {
    global $CFG, $DB;

    require_once($CFG->libdir . '/enrollib.php');

    $course = local_kaznu_get_demo_course();
    if (!$course) {
        return;
    }

    $DB->set_field('course', 'visible', 0, ['id' => $course->id]);

    $role = $DB->get_record('role', ['shortname' => 'student']);
    if (!$role) {
        return;
    }
    $context = context_course::instance($course->id);
    foreach (enrol_get_instances($course->id, true) as $instance) {
        $enrolplugin = enrol_get_plugin($instance->enrol);
        if (!$enrolplugin) {
            continue;
        }
        $userids = $DB->get_fieldset_select(
            'user_enrolments',
            'userid',
            'enrolid = ? AND status = ?',
            [$instance->id, ENROL_USER_ACTIVE]
        );
        foreach ($userids as $userid) {
            $userid = (int) $userid;
            $roles = get_user_roles($context, $userid, false);
            $keep = false;
            foreach ($roles as $r) {
                if (in_array($r->shortname, ['editingteacher', 'teacher', 'manager'], true)) {
                    $keep = true;
                    break;
                }
            }
            if (!$keep) {
                $enrolplugin->update_user_enrol($instance, $userid, ENROL_USER_SUSPENDED);
            }
        }
    }
}

/**
 * Ordered visible activities with URLs in a course (for resume / prev-next).
 *
 * @return cm_info[]
 */
function local_kaznu_course_activity_sequence(stdClass $course, int $userid = 0): array {
    $modinfo = get_fast_modinfo($course, $userid);
    $list = [];
    foreach ($modinfo->get_section_info_all() as $section) {
        if ((int) $section->section === 0) {
            continue;
        }
        if (empty($modinfo->sections[$section->section])) {
            continue;
        }
        foreach ($modinfo->sections[$section->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible || !$cm->url) {
                continue;
            }
            $list[] = $cm;
        }
    }
    return $list;
}

/**
 * Prev / next activity around the current CM.
 *
 * @return array{prev:?cm_info,next:?cm_info,index:int,total:int}
 */
function local_kaznu_activity_neighbours(stdClass $course, int $cmid, int $userid = 0): array {
    $seq = local_kaznu_course_activity_sequence($course, $userid);
    $prev = null;
    $next = null;
    $index = -1;
    $total = count($seq);
    foreach ($seq as $i => $cm) {
        if ((int) $cm->id === $cmid) {
            $index = $i;
            if ($i > 0) {
                $prev = $seq[$i - 1];
            }
            if ($i < $total - 1) {
                $next = $seq[$i + 1];
            }
            break;
        }
    }
    return ['prev' => $prev, 'next' => $next, 'index' => $index, 'total' => $total];
}

/**
 * Resume URL for enrolled user: first incomplete activity, or course hub.
 */
function local_kaznu_resume_url(stdClass $course, int $userid): moodle_url {
    try {
        $progress = local_kaznu_get_course_progress($course, $userid);
        if (!empty($progress['nexturl'])) {
            return $progress['nexturl'];
        }
    } catch (Throwable $e) {
        debugging('local_kaznu_resume_url: ' . $e->getMessage(), DEBUG_DEVELOPER);
    }
    // Prefer branded hub over Moodle course home (which may auto-open first file).
    return new moodle_url('/local/kaznu/course.php', ['id' => $course->id]);
}

/**
 * CTA label: start vs continue based on completed activities.
 */
function local_kaznu_resume_label(stdClass $course, int $userid): string {
    try {
        $progress = local_kaznu_get_course_progress($course, $userid);
        return $progress['done'] > 0
            ? get_string('arena_continue', 'local_kaznu')
            : get_string('arena_start', 'local_kaznu');
    } catch (Throwable $e) {
        return get_string('landing_cta_continue', 'local_kaznu');
    }
}

/**
 * Human label for stored badge key.
 */
function local_kaznu_badge_label(string $key): string {
    $map = [
        'first_steps' => 'badge_first_steps',
        'quiz_pass' => 'badge_quiz_pass',
        'perfect_score' => 'badge_perfect',
    ];
    $stringkey = $map[$key] ?? '';
    if ($stringkey !== '' && get_string_manager()->string_exists($stringkey, 'local_kaznu')) {
        return get_string($stringkey, 'local_kaznu');
    }
    return $key;
}

/**
 * Course completion progress for gamified arena.
 *
 * @return array{done:int,total:int,pct:int,nexturl:?moodle_url,nextname:string,nextisfile:bool,sections:array}
 */
function local_kaznu_get_course_progress(stdClass $course, int $userid): array {
    global $CFG;

    // Must import $CFG into function scope — completionlib.php reads $CFG->dirroot locally.
    require_once($CFG->libdir . '/completionlib.php');

    $modinfo = get_fast_modinfo($course, $userid);
    $completion = new completion_info($course);
    $done = 0;
    $total = 0;
    $nexturl = null;
    $nextname = '';
    $nextisfile = false;
    $sections = [];

    foreach ($modinfo->get_section_info_all() as $section) {
        if ((int) $section->section === 0) {
            continue;
        }
        $secdone = 0;
        $sectotal = 0;
        $cms = [];
        if (!empty($modinfo->sections[$section->section])) {
            foreach ($modinfo->sections[$section->section] as $cmid) {
                $cm = $modinfo->cms[$cmid];
                if (!$cm->uservisible && empty($cm->availableinfo)) {
                    continue;
                }
                if (!$cm->url) {
                    continue;
                }
                $sectotal++;
                $total++;
                $complete = false;
                if ($completion->is_enabled($cm)) {
                    $cdata = $completion->get_data($cm, false, $userid);
                    $complete = in_array((int) $cdata->completionstate, [
                        COMPLETION_COMPLETE,
                        COMPLETION_COMPLETE_PASS,
                    ], true);
                }
                $isfile = in_array($cm->modname, ['resource', 'folder', 'lightboxgallery'], true);
                if ($complete) {
                    $secdone++;
                    $done++;
                } else if ($cm->uservisible) {
                    // First incomplete activity in syllabus order (PDF embeds in-page now).
                    if ($nexturl === null) {
                        $nexturl = $cm->url;
                        $nextname = $cm->name;
                        $nextisfile = $isfile;
                    }
                }
                $cms[] = (object) [
                    'name' => $cm->name,
                    'complete' => $complete,
                    'available' => $cm->uservisible,
                ];
            }
        }
        $sections[] = (object) [
            'num' => (int) $section->section,
            'name' => get_section_name($course, $section),
            'available' => !empty($section->available),
            'done' => $secdone,
            'total' => $sectotal,
            'cms' => $cms,
        ];
    }

    if ($nexturl === null) {
        foreach ($modinfo->cms as $cm) {
            if ($cm->uservisible && $cm->url) {
                $nexturl = $cm->url;
                $nextname = $cm->name;
                $nextisfile = in_array($cm->modname, ['resource', 'folder', 'lightboxgallery'], true);
                break;
            }
        }
    }

    $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;
    return [
        'done' => $done,
        'total' => $total,
        'pct' => $pct,
        'nexturl' => $nexturl,
        'nextname' => $nextname,
        'nextisfile' => $nextisfile,
        'sections' => $sections,
    ];
}

/**
 * HTML for gamified course arena (injected on course/mod pages).
 */
function local_kaznu_render_course_arena(stdClass $course, int $userid): string {
    $accent = local_kaznu_course_accent($course);
    $progress = local_kaznu_get_course_progress($course, $userid);
    $xp = local_kaznu_get_xp($userid);
    $xpprog = local_kaznu_xp_progress($xp);
    $catalog = new moodle_url('/local/kaznu/landing.php');
    $hub = new moodle_url('/local/kaznu/course.php', ['id' => $course->id]);

    $ctaurl = $progress['nexturl'] ?: new moodle_url('/course/view.php', ['id' => $course->id]);
    $ctalabel = $progress['done'] > 0
        ? get_string('arena_continue', 'local_kaznu')
        : get_string('arena_start', 'local_kaznu');
    $ctahint = '';
    if ($progress['done'] > 0 && $progress['nextname'] !== '') {
        $ctahint = '<span class="kzn-arena-cta-hint">' . get_string('arena_resume_hint', 'local_kaznu', format_string($progress['nextname'])) . '</span>';
    }

    $pathhtml = '';
    foreach (array_slice($progress['sections'], 0, 8) as $sec) {
        $state = $sec->available ? 'is-open' : 'is-locked';
        if ($sec->total > 0 && $sec->done >= $sec->total) {
            $state = 'is-done';
        }
        $pathhtml .= '<li class="' . $state . '">'
            . '<span class="kzn-arena-dot"></span>'
            . '<span class="kzn-arena-secname">' . format_string($sec->name) . '</span>'
            . '</li>';
    }

    $doneobj = (object) ['done' => $progress['done'], 'total' => max(1, $progress['total'])];

    return '<div class="local-kaznu-arena kzn-accent-' . $accent . '" data-kaznu-arena="1">'
        . '<div class="kzn-arena-inner">'
        . '<div class="kzn-arena-top">'
        . '<a class="kzn-arena-back" href="' . $catalog->out(false) . '">' . get_string('arena_back', 'local_kaznu') . '</a>'
        . '<span class="kzn-arena-short">' . s($course->shortname) . '</span>'
        . '</div>'
        . '<h1 class="kzn-arena-title">' . format_string($course->fullname) . '</h1>'
        . '<p class="kzn-arena-reward">' . get_string('arena_reward', 'local_kaznu', LOCAL_KAZNU_XP_QUIZ_PASS) . '</p>'
        . '<div class="kzn-arena-stats">'
        . '<div class="kzn-arena-stat">'
        . '<label>' . get_string('arena_progress', 'local_kaznu') . '</label>'
        . '<strong>' . get_string('arena_done', 'local_kaznu', $doneobj) . '</strong>'
        . '<div class="kzn-arena-bar"><span style="width:' . (int) $progress['pct'] . '%"></span></div>'
        . '</div>'
        . '<div class="kzn-arena-stat">'
        . '<label>' . get_string('arena_xp_label', 'local_kaznu') . '</label>'
        . '<strong>' . s($xpprog['title']) . ' · Lv ' . (int) $xp->level . '</strong>'
        . '<div class="kzn-arena-bar kzn-arena-bar-xp"><span style="width:' . (int) $xpprog['pct'] . '%"></span></div>'
        . '<em>' . (int) $xp->xp . ' XP</em>'
        . '</div>'
        . '</div>'
        . '<div class="kzn-arena-actions">'
        . '<a class="kzn-arena-cta" href="' . $ctaurl->out(false) . '">' . $ctalabel . '</a>'
        . $ctahint
        . '<a class="kzn-arena-link" href="' . $hub->out(false) . '">' . get_string('hub_syllabus', 'local_kaznu') . '</a>'
        . '</div>'
        . '<div class="kzn-arena-path">'
        . '<h2>' . get_string('arena_path', 'local_kaznu') . '</h2>'
        . '<ol>' . $pathhtml . '</ol>'
        . '</div>'
        . '</div></div>';
}

/**
 * Enrolled courses for the current user (catalogue only).
 *
 * @return stdClass[]
 */
function local_kaznu_get_user_courses(int $userid): array {
    $all = [];
    foreach (enrol_get_users_courses($userid, true, 'id,shortname,fullname,visible,sortorder') as $c) {
        if ((int) $c->id <= 1) {
            continue;
        }
        if (local_kaznu_is_retired_course($c)) {
            continue;
        }
        $all[] = $c;
    }
    return $all;
}

/**
 * Language dropdown HTML (adway-style).
 */
function local_kaznu_render_lang_dropdown(?moodle_url $baseurl = null): string {
    global $CFG, $PAGE;

    $current = current_language();
    $langs = [
        'kk' => 'Қаз',
        'ru' => 'Рус',
        'en' => 'Eng',
        'zh_cn' => '中文',
    ];
    $currentlabel = $langs[$current] ?? strtoupper($current);

    if ($baseurl === null) {
        $baseurl = $PAGE->url ?: new moodle_url('/local/kaznu/landing.php');
    }

    $items = '';
    foreach ($langs as $code => $label) {
        $url = new moodle_url($baseurl, ['lang' => $code]);
        $active = ($code === $current) ? ' is-active' : '';
        $items .= '<a class="kzn-lang-item' . $active . '" href="' . $url->out(false) . '" lang="' . s($code) . '">'
            . s($label) . '</a>';
    }

    return '<div class="kzn-lang-dd" data-kaznu-lang>'
        . '<button type="button" class="kzn-lang-btn" aria-haspopup="listbox" aria-expanded="false">'
        . '<span>' . s($currentlabel) . '</span>'
        . '<svg width="10" height="6" viewBox="0 0 10 6" aria-hidden="true"><path d="M1 1l4 4 4-4" stroke="currentColor" stroke-width="1.4" fill="none"/></svg>'
        . '</button>'
        . '<div class="kzn-lang-menu" hidden role="listbox">' . $items . '</div>'
        . '</div>';
}

/**
 * Mock calendar events for demo tab.
 *
 * @return array<int,object>
 */
function local_kaznu_mock_calendar_events(): array {
    $now = time();
    return [
        (object) [
            'date' => userdate($now + DAYSECS * 2, '%d.%m.%Y'),
            'title' => get_string('dash_cal_mock1', 'local_kaznu'),
            'meta' => get_string('dash_cal_webinar', 'local_kaznu'),
        ],
        (object) [
            'date' => userdate($now + DAYSECS * 5, '%d.%m.%Y'),
            'title' => get_string('dash_cal_mock2', 'local_kaznu'),
            'meta' => get_string('dash_cal_deadline', 'local_kaznu'),
        ],
        (object) [
            'date' => userdate($now + DAYSECS * 12, '%d.%m.%Y'),
            'title' => get_string('dash_cal_mock3', 'local_kaznu'),
            'meta' => get_string('dash_cal_exam', 'local_kaznu'),
        ],
    ];
}

/**
 * Grade rows for dashboard tab (real if any, else mock).
 *
 * @return array<int,object>
 */
function local_kaznu_dashboard_grades(int $userid): array {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    $rows = [];
    foreach (local_kaznu_get_user_courses($userid) as $course) {
        $gis = grade_item::fetch_all(['courseid' => $course->id, 'itemtype' => 'mod']);
        if (!$gis) {
            continue;
        }
        foreach ($gis as $gi) {
            $grade = new grade_grade(['itemid' => $gi->id, 'userid' => $userid]);
            if ($grade->finalgrade === null) {
                continue;
            }
            $pct = $gi->grademax > 0 ? round(((float) $grade->finalgrade / (float) $gi->grademax) * 100) : 0;
            $rows[] = (object) [
                'course' => format_string($course->fullname),
                'item' => $gi->get_name(),
                'grade' => $pct . '%',
                'status' => $pct >= 60
                    ? get_string('dash_grade_pass', 'local_kaznu')
                    : get_string('dash_grade_fail', 'local_kaznu'),
                'pass' => $pct >= 60,
            ];
        }
    }

    if ($rows) {
        return array_slice($rows, 0, 12);
    }

    // Mock while gradebook is empty.
    return [
        (object) [
            'course' => 'Farabi Summer School 2026',
            'item' => get_string('dash_grade_mock1', 'local_kaznu'),
            'grade' => '80%',
            'status' => get_string('dash_grade_pass', 'local_kaznu'),
            'pass' => true,
        ],
        (object) [
            'course' => 'Farabi Summer School 2026',
            'item' => get_string('dash_grade_mock2', 'local_kaznu'),
            'grade' => '—',
            'status' => get_string('dash_grade_pending', 'local_kaznu'),
            'pass' => false,
        ],
        (object) [
            'course' => 'EMBA Corporate Finance',
            'item' => get_string('dash_grade_mock3', 'local_kaznu'),
            'grade' => '65%',
            'status' => get_string('dash_grade_pass', 'local_kaznu'),
            'pass' => true,
        ],
    ];
}

/**
 * Gamified dashboard banner for student pages.
 */
function local_kaznu_render_dashboard_arena(stdClass $user): string {
    $xp = local_kaznu_get_xp((int) $user->id);
    $xpprog = local_kaznu_xp_progress($xp);
    $courses = local_kaznu_get_user_courses((int) $user->id);
    $catalog = new moodle_url('/local/kaznu/landing.php');
    $tonext = LOCAL_KAZNU_XP_PER_LEVEL - $xpprog['into'];
    $level = (int) $xp->level;
    $maxlevel = LOCAL_KAZNU_MAX_LEVEL;

    $badges = json_decode($xp->badges ?: '[]', true);
    if (!is_array($badges)) {
        $badges = [];
    }
    $badgehtml = '';
    if ($badges) {
        foreach ($badges as $b) {
            $b = (string) $b;
            if (strpos($b, 'quiz:') === 0) {
                continue; // Internal award lock, not a display badge.
            }
            $badgehtml .= '<span class="kzn-dash-badge">' . s(local_kaznu_badge_label($b)) . '</span>';
        }
    }
    if ($badgehtml === '') {
        $badgehtml = '<span class="kzn-dash-badge is-empty">' . get_string('badge_none', 'local_kaznu') . '</span>';
    }

    $firstname = !empty($user->firstname) ? $user->firstname : fullname($user);

    $tiles = '';
    foreach (array_slice($courses, 0, 6) as $c) {
        $accent = local_kaznu_course_accent($c);
        // Open class hub — avoid jumping into PDF/file activities from the dashboard.
        $url = new moodle_url('/local/kaznu/course.php', ['id' => $c->id]);
        $label = local_kaznu_resume_label($c, (int) $user->id);
        $tiles .= '<a class="kzn-dash-tile kzn-accent-' . $accent . '" href="' . $url->out(false) . '">'
            . '<span class="kzn-dash-tile-meta">' . s($c->shortname) . '</span>'
            . '<strong>' . format_string($c->fullname) . '</strong>'
            . '<em>' . $label . '</em>'
            . '</a>';
    }
    if ($tiles === '') {
        $tiles = '<p class="kzn-dash-empty">' . get_string('dash_empty', 'local_kaznu') . '</p>';
    }

    $calevents = '';
    foreach (local_kaznu_mock_calendar_events() as $ev) {
        $calevents .= '<li class="kzn-cal-item">'
            . '<time>' . s($ev->date) . '</time>'
            . '<div><strong>' . s($ev->title) . '</strong><span>' . s($ev->meta) . '</span></div>'
            . '</li>';
    }

    $graderows = '';
    foreach (local_kaznu_dashboard_grades((int) $user->id) as $g) {
        $tone = !empty($g->pass) ? 'is-pass' : 'is-pending';
        $graderows .= '<tr class="' . $tone . '">'
            . '<td>' . s($g->course) . '</td>'
            . '<td>' . s($g->item) . '</td>'
            . '<td>' . s($g->grade) . '</td>'
            . '<td>' . s($g->status) . '</td>'
            . '</tr>';
    }

    $rankstats = '<ul class="kzn-dash-rank-stats">'
        . '<li><span>' . get_string('dash_level_current', 'local_kaznu') . '</span>'
        . '<strong>Lv ' . $level . ' · ' . s($xpprog['title']) . '</strong></li>'
        . '<li><span>' . get_string('dash_level_total', 'local_kaznu') . '</span>'
        . '<strong>' . $maxlevel . '</strong></li>'
        . '<li><span>' . get_string('dash_level_to_next', 'local_kaznu') . '</span>'
        . '<strong>' . ($level >= $maxlevel ? get_string('dash_level_max', 'local_kaznu') : $tonext . ' XP') . '</strong></li>'
        . '</ul>';

    $tabs = '<div class="kzn-dash-tabs" data-kaznu-tabs>'
        . '<div class="kzn-dash-tablist" role="tablist">'
        . '<button type="button" role="tab" aria-selected="true" data-tab="courses">' . get_string('dash_tab_courses', 'local_kaznu') . '</button>'
        . '<button type="button" role="tab" aria-selected="false" data-tab="calendar">' . get_string('dash_tab_calendar', 'local_kaznu') . '</button>'
        . '<button type="button" role="tab" aria-selected="false" data-tab="grades">' . get_string('dash_tab_grades', 'local_kaznu') . '</button>'
        . '</div>'
        . '<div class="kzn-dash-tabpanel is-active" data-panel="courses" role="tabpanel">'
        . '<h2>' . get_string('dash_courses', 'local_kaznu') . '</h2>'
        . '<div class="kzn-dash-tiles">' . $tiles . '</div>'
        . '</div>'
        . '<div class="kzn-dash-tabpanel" data-panel="calendar" role="tabpanel" hidden>'
        . '<h2>' . get_string('dash_tab_calendar', 'local_kaznu') . '</h2>'
        . '<p class="kzn-dash-tab-lead">' . get_string('dash_cal_lead', 'local_kaznu') . '</p>'
        . '<ul class="kzn-cal-list">' . $calevents . '</ul>'
        . '</div>'
        . '<div class="kzn-dash-tabpanel" data-panel="grades" role="tabpanel" hidden>'
        . '<h2>' . get_string('dash_tab_grades', 'local_kaznu') . '</h2>'
        . '<p class="kzn-dash-tab-lead">' . get_string('dash_grades_lead', 'local_kaznu') . '</p>'
        . '<div class="kzn-grades-wrap"><table class="kzn-grades-table">'
        . '<thead><tr>'
        . '<th>' . get_string('dash_grade_course', 'local_kaznu') . '</th>'
        . '<th>' . get_string('dash_grade_item', 'local_kaznu') . '</th>'
        . '<th>' . get_string('dash_grade_score', 'local_kaznu') . '</th>'
        . '<th>' . get_string('dash_grade_status', 'local_kaznu') . '</th>'
        . '</tr></thead><tbody>' . $graderows . '</tbody></table></div>'
        . '</div>'
        . '</div>';

    $tabscript = '<script>(function(){var root=document.querySelector("[data-kaznu-tabs]");if(!root)return;'
        . 'var btns=root.querySelectorAll("[data-tab]");var panels=root.querySelectorAll("[data-panel]");'
        . 'btns.forEach(function(b){b.addEventListener("click",function(){'
        . 'btns.forEach(function(x){x.setAttribute("aria-selected","false");});'
        . 'panels.forEach(function(p){p.hidden=true;p.classList.remove("is-active");});'
        . 'b.setAttribute("aria-selected","true");'
        . 'var p=root.querySelector("[data-panel=\\""+b.getAttribute("data-tab")+"\\"]");'
        . 'if(p){p.hidden=false;p.classList.add("is-active");}'
        . '});});})();</script>';

    return '<div class="local-kaznu-dash" data-kaznu-dash="1">'
        . '<div class="kzn-dash-hero">'
        . '<p class="kzn-dash-hello">' . get_string('dash_hello', 'local_kaznu', s($firstname)) . '</p>'
        . '<h1>' . get_string('dash_title', 'local_kaznu') . '</h1>'
        . '<p class="kzn-dash-lead">' . get_string('dash_lead', 'local_kaznu') . '</p>'
        . '<div class="kzn-dash-xp">'
        . '<div class="kzn-dash-xp-meta">'
        . '<strong>' . get_string('dash_rank', 'local_kaznu') . ': ' . s($xpprog['title']) . '</strong>'
        . '<span>Lv ' . $level . ' · ' . (int) $xp->xp . ' XP</span>'
        . '</div>'
        . '<div class="kzn-arena-bar"><span style="width:' . (int) $xpprog['pct'] . '%"></span></div>'
        . '<em>' . get_string('xp_to_next', 'local_kaznu', $tonext) . '</em>'
        . '</div>'
        . $rankstats
        . '<div class="kzn-dash-achievements">'
        . '<h3>' . get_string('dash_achievements', 'local_kaznu') . '</h3>'
        . '<div class="kzn-dash-badges">' . $badgehtml . '</div>'
        . '</div>'
        . '<details class="kzn-dash-ranks-info">'
        . '<summary>' . get_string('dash_ranks_info', 'local_kaznu') . '</summary>'
        . '<ul class="kzn-xp-rules">'
        . '<li>' . get_string('xp_rule_quiz', 'local_kaznu', LOCAL_KAZNU_XP_QUIZ_PASS) . '</li>'
        . '<li>' . get_string('xp_rule_perfect', 'local_kaznu', LOCAL_KAZNU_XP_QUIZ_PERFECT) . '</li>'
        . '<li>' . get_string('xp_rule_enrol', 'local_kaznu', LOCAL_KAZNU_XP_ENROL) . '</li>'
        . '<li>' . get_string('xp_rule_level', 'local_kaznu', LOCAL_KAZNU_XP_PER_LEVEL) . '</li>'
        . '</ul>'
        . '</details>'
        . '<div class="kzn-arena-actions">'
        . '<a class="kzn-arena-cta" href="' . $catalog->out(false) . '">' . get_string('dash_catalog', 'local_kaznu') . '</a>'
        . '</div>'
        . '</div>'
        . $tabs
        . $tabscript
        . '</div>';
}

/**
 * Prev / next strip + optional next-module CTA after quiz.
 */
function local_kaznu_render_lesson_nav(stdClass $course, int $cmid, int $userid, bool $afternext = false): string {
    $n = local_kaznu_activity_neighbours($course, $cmid, $userid);
    $html = '<nav class="local-kaznu-lesson-nav" data-kaznu-nav="1" aria-label="' . get_string('lesson_nav', 'local_kaznu') . '">';
    if ($n['prev']) {
        $html .= '<a class="kzn-lesson-prev" href="' . $n['prev']->url->out(false) . '">'
            . get_string('lesson_prev', 'local_kaznu') . '</a>';
    } else {
        $html .= '<span class="kzn-lesson-prev is-disabled">' . get_string('lesson_prev', 'local_kaznu') . '</span>';
    }
    if ($n['index'] >= 0) {
        $html .= '<span class="kzn-lesson-pos">' . ($n['index'] + 1) . ' / ' . $n['total'] . '</span>';
    }
    if ($n['next']) {
        $nextlabel = $afternext
            ? get_string('lesson_next_module', 'local_kaznu')
            : get_string('lesson_next', 'local_kaznu');
        $html .= '<a class="kzn-lesson-next" href="' . $n['next']->url->out(false) . '">' . $nextlabel . '</a>';
    } else {
        $html .= '<span class="kzn-lesson-next is-disabled">' . get_string('lesson_next', 'local_kaznu') . '</span>';
    }
    $html .= '</nav>';
    return $html;
}

/**
 * Extract YouTube embed HTML from a URL activity externalurl.
 */
function local_kaznu_youtube_embed_from_url(string $url): string {
    $id = '';
    if (preg_match('~(?:youtube\.com/watch\?v=|youtu\.be/|youtube\.com/embed/)([a-zA-Z0-9_-]{6,})~', $url, $m)) {
        $id = $m[1];
    }
    if ($id === '') {
        return '';
    }
    $src = 'https://www.youtube.com/embed/' . rawurlencode($id) . '?rel=0&modestbranding=1';
    return '<div class="local-kaznu-yt" data-kaznu-yt="1">'
        . '<iframe src="' . s($src) . '" title="YouTube" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen loading="lazy"></iframe>'
        . '</div>';
}

/**
 * Extract Vimeo embed HTML from a URL.
 */
function local_kaznu_vimeo_embed_from_url(string $url): string {
    if (!preg_match('~vimeo\.com/(?:video/)?(\d+)~', $url, $m)) {
        return '';
    }
    $src = 'https://player.vimeo.com/video/' . rawurlencode($m[1]);
    return '<div class="local-kaznu-yt" data-kaznu-yt="1">'
        . '<iframe src="' . s($src) . '" title="Vimeo" allow="autoplay; fullscreen; picture-in-picture" allowfullscreen loading="lazy"></iframe>'
        . '</div>';
}

/**
 * Quiz page panel: how much XP you can earn / already earned / why none.
 */
function local_kaznu_render_quiz_xp_panel(stdClass $course, int $userid, int $quizid): string {
    global $DB;

    // Never render achievements UI during active quiz attempt pages.
    if (local_kaznu_is_quiz_focus_page()) {
        return '';
    }

    $pass = LOCAL_KAZNU_XP_QUIZ_PASS;
    $perfect = LOCAL_KAZNU_XP_QUIZ_PERFECT;
    $hint = get_string('xp_quiz_hint', 'local_kaznu', (object) [
        'pass' => $pass,
        'perfect' => $perfect,
        'total' => $pass + $perfect,
    ]);

    $rec = local_kaznu_get_xp($userid);
    $badges = json_decode($rec->badges ?: '[]', true);
    if (!is_array($badges)) {
        $badges = [];
    }
    $awarded = in_array('quiz:' . $quizid, $badges, true);

    $status = '';
    $cls = 'is-hint';
    if ($awarded) {
        $status = get_string('xp_quiz_awarded', 'local_kaznu');
        $cls = 'is-ok';
    } else {
        $gi = $DB->get_record('grade_items', [
            'courseid' => $course->id,
            'itemmodule' => 'quiz',
            'iteminstance' => $quizid,
        ]);
        $grade = null;
        $max = 100.0;
        if ($gi) {
            $max = (float) ($gi->grademax ?: 100);
            $gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $userid]);
            if ($gg && $gg->finalgrade !== null) {
                $grade = (float) $gg->finalgrade;
            }
        }
        $openattempt = $DB->record_exists_select(
            'quiz_attempts',
            'quiz = ? AND userid = ? AND state <> ?',
            [$quizid, $userid, 'finished']
        );
        if ($openattempt) {
            $status = get_string('xp_quiz_need_submit', 'local_kaznu');
            $cls = 'is-warn';
        } else if ($grade !== null) {
            $pct = $max > 0 ? ($grade / $max) * 100.0 : $grade;
            if ($pct < 60.0 && $grade < 60.0) {
                $status = get_string('xp_quiz_failed', 'local_kaznu');
                $cls = 'is-warn';
            }
        }
    }

    $body = '<div class="kzn-xp-panel-inner ' . $cls . '">'
        . '<p class="kzn-xp-panel-hint">' . $hint . '</p>';
    if ($status !== '') {
        $body .= '<p class="kzn-xp-panel-status">' . $status . '</p>';
    }
    $tonext = LOCAL_KAZNU_XP_PER_LEVEL - ((int) $rec->xp % LOCAL_KAZNU_XP_PER_LEVEL);
    if ($tonext === LOCAL_KAZNU_XP_PER_LEVEL && (int) $rec->xp > 0 && ((int) $rec->xp % LOCAL_KAZNU_XP_PER_LEVEL) === 0) {
        $tonext = LOCAL_KAZNU_XP_PER_LEVEL;
    }
    $body .= '<p class="kzn-xp-panel-to-next">' . get_string('xp_to_next', 'local_kaznu', $tonext) . '</p>'
        . '</div>';

    return local_kaznu_render_achievement_panel([
        'id' => 'kzn-xp-panel-q' . $quizid,
        'title' => get_string('xp_quiz_panel_title', 'local_kaznu'),
        'level' => (int) $rec->level,
        'xp' => (int) $rec->xp,
        'bodyhtml' => $body,
        'variant' => 'inline',
        'extra_class' => 'kzn-xp-panel',
    ]);
}

/**
 * Force YouTube/Vimeo URL activities to Embed display (iframe, not new window / click link).
 */
function local_kaznu_fix_url_video_embed_display(): int {
    global $CFG, $DB;

    require_once($CFG->libdir . '/resourcelib.php');

    $n = 0;
    foreach ($DB->get_records('url', null, '', 'id,externalurl,display') as $rec) {
        $url = (string) $rec->externalurl;
        $isyoutube = (bool) preg_match('~youtube\.com|youtu\.be~i', $url);
        $isvimeo = (bool) preg_match('~vimeo\.com~i', $url);
        if (!$isyoutube && !$isvimeo) {
            continue;
        }
        if ((int) $rec->display === RESOURCELIB_DISPLAY_EMBED) {
            continue;
        }
        $DB->set_field('url', 'display', RESOURCELIB_DISPLAY_EMBED, ['id' => $rec->id]);
        $n++;
    }
    return $n;
}

/**
 * Main file URL for a resource activity (inline, not forcedownload).
 */
function local_kaznu_resource_main_file_url(int $cmid): string {
    $cm = get_coursemodule_from_id('resource', $cmid, 0, false);
    if (!$cm) {
        return '';
    }
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
    if (!$files) {
        return '';
    }
    $file = reset($files);
    foreach ($files as $candidate) {
        $mimetype = $candidate->get_mimetype();
        if ($mimetype === 'application/pdf' || preg_match('~\.pdf$~i', $candidate->get_filename())) {
            $file = $candidate;
            break;
        }
    }
    $url = moodle_url::make_pluginfile_url(
        $file->get_contextid(),
        $file->get_component(),
        $file->get_filearea(),
        $file->get_itemid(),
        $file->get_filepath(),
        $file->get_filename(),
        false
    );
    return $url->out(false);
}

/**
 * Whether a resource file should be shown in an iframe (PDF / Office / text / image).
 */
function local_kaznu_resource_is_embeddable(int $cmid): bool {
    $cm = get_coursemodule_from_id('resource', $cmid, 0, false);
    if (!$cm) {
        return false;
    }
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_resource', 'content', 0, 'sortorder DESC, id ASC', false);
    if (!$files) {
        return false;
    }
    $file = reset($files);
    $name = core_text::strtolower($file->get_filename());
    $mime = (string) $file->get_mimetype();
    if (str_ends_with($name, '.pdf') || $mime === 'application/pdf') {
        return true;
    }
    if (str_starts_with($mime, 'image/') || str_starts_with($mime, 'text/')) {
        return true;
    }
    return (bool) preg_match('~\.(png|jpe?g|gif|webp|svg|txt|html?)$~', $name);
}

/**
 * In-page PDF/file embed HTML for resource view.
 */
function local_kaznu_render_resource_embed(int $cmid, string $title = ''): string {
    if (!local_kaznu_resource_is_embeddable($cmid)) {
        return '';
    }
    $src = local_kaznu_resource_main_file_url($cmid);
    if ($src === '') {
        return '';
    }
    $label = $title !== '' ? $title : get_string('hub_pdf_viewer', 'local_kaznu');
    return '<div class="local-kaznu-yt local-kaznu-pdf" data-kaznu-pdf="1">'
        . '<iframe src="' . s($src) . '#view=FitH" title="' . s($label) . '" loading="lazy"></iframe>'
        . '</div>';
}

/**
 * Force resource files to Embed + enable "mark complete on view".
 *
 * @return array{display:int,completion:int}
 */
function local_kaznu_fix_resource_pdf_embed_display(): array {
    global $CFG, $DB;

    require_once($CFG->libdir . '/resourcelib.php');
    require_once($CFG->libdir . '/completionlib.php');

    $displayfixed = 0;
    $completionfixed = 0;
    $options = serialize(['printintro' => 0, 'printlastmodified' => 0]);

    foreach ($DB->get_records('resource', null, '', 'id,display,displayoptions') as $rec) {
        $need = false;
        if ((int) $rec->display !== RESOURCELIB_DISPLAY_EMBED) {
            $rec->display = RESOURCELIB_DISPLAY_EMBED;
            $need = true;
            $displayfixed++;
        }
        // Keep embed options lean so Moodle does not push "open in new window".
        if ((string) $rec->displayoptions !== $options) {
            $rec->displayoptions = $options;
            $need = true;
        }
        if ($need) {
            $DB->update_record('resource', $rec);
        }
    }

    $sql = "SELECT cm.id, cm.completion, cm.completionview
              FROM {course_modules} cm
              JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'";
    foreach ($DB->get_records_sql($sql) as $cm) {
        $changed = false;
        if ((int) $cm->completion === COMPLETION_TRACKING_NONE) {
            $cm->completion = COMPLETION_TRACKING_AUTOMATIC;
            $changed = true;
        }
        if ((int) $cm->completionview !== 1) {
            $cm->completionview = 1;
            $changed = true;
        }
        if ($changed) {
            $DB->update_record('course_modules', $cm);
            $completionfixed++;
        }
    }

    return ['display' => $displayfixed, 'completion' => $completionfixed];
}