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

/**
 * Demo / featured summer course or null.
 */
function local_kaznu_get_demo_course(): ?stdClass {
    global $DB;
    return $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]) ?: null;
}

/**
 * All catalogue courses (site courses except frontpage).
 *
 * @return stdClass[]
 */
function local_kaznu_get_catalogue_courses(bool $includehidden = true): array {
    global $DB;

    $params = [];
    $sql = "SELECT * FROM {course} WHERE id > 1";
    if (!$includehidden) {
        $sql .= " AND visible = 1";
    }
    $sql .= " ORDER BY sortorder ASC, fullname ASC";
    return array_values($DB->get_records_sql($sql, $params));
}

/**
 * Shelf key for MasterClass-style rows.
 */
function local_kaznu_course_shelf(stdClass $course): string {
    $sn = strtoupper($course->shortname);
    if ($sn === 'SUMMER2026' || stripos($course->fullname, 'летн') !== false || stripos($course->fullname, 'summer') !== false) {
        return 'summer';
    }
    if (in_array($sn, ['BRM', 'RM'], true) || stripos($course->fullname, 'research') !== false) {
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
    if (($levelup || $amount >= LOCAL_KAZNU_XP_QUIZ_PASS) && isset($GLOBALS['SESSION'])) {
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
 * Leaderboard top N.
 *
 * @return array<int,stdClass>
 */
function local_kaznu_leaderboard(int $limit = 10): array {
    global $DB;

    $dbman = $DB->get_manager();
    if (!$dbman->table_exists('local_kaznu_xp')) {
        return [];
    }

    $sql = "SELECT x.*, u.firstname, u.lastname, u.firstnamephonetic, u.lastnamephonetic, u.middlename, u.alternatename
              FROM {local_kaznu_xp} x
              JOIN {user} u ON u.id = x.userid
             WHERE u.deleted = 0 AND u.suspended = 0 AND x.xp > 0
          ORDER BY x.xp DESC, x.timemodified ASC";
    return array_values($DB->get_records_sql($sql, [], 0, $limit));
}

/**
 * Mark quiz CM complete with pass grade for one user (>= 60%).
 */
function local_kaznu_sync_user_quiz_completion(stdClass $course, int $userid, int $quizid): bool {
    global $DB;

    require_once($GLOBALS['CFG']->libdir . '/completionlib.php');

    $cm = get_coursemodule_from_instance('quiz', $quizid, $course->id);
    if (!$cm || (int) $cm->completion !== COMPLETION_TRACKING_AUTOMATIC) {
        return false;
    }

    $gi = $DB->get_record('grade_items', [
        'courseid' => $course->id,
        'itemmodule' => 'quiz',
        'iteminstance' => $quizid,
    ]);
    if (!$gi) {
        return false;
    }

    $gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $userid]);
    if (!$gg || $gg->finalgrade === null || (float) $gg->finalgrade < 60) {
        return false;
    }

    $state = COMPLETION_COMPLETE_PASS;
    $now = time();
    $rec = $DB->get_record('course_modules_completion', [
        'coursemoduleid' => $cm->id,
        'userid' => $userid,
    ]);
    if ($rec) {
        if ((int) $rec->completionstate === $state) {
            return true;
        }
        $rec->completionstate = $state;
        $rec->timemodified = $now;
        $DB->update_record('course_modules_completion', $rec);
    } else {
        $DB->insert_record('course_modules_completion', (object) [
            'coursemoduleid' => $cm->id,
            'userid' => $userid,
            'completionstate' => $state,
            'timemodified' => $now,
        ]);
    }

    $completion = new completion_info($course);
    $completion->update_state($cm, $state, $userid, true);
    rebuild_course_cache($course->id, true);

    $grade = (float) $gg->finalgrade;
    $xp = LOCAL_KAZNU_XP_QUIZ_PASS;
    $badge = 'quiz_pass';
    if ($grade >= 100) {
        $xp += LOCAL_KAZNU_XP_QUIZ_PERFECT;
        $badge = 'perfect_score';
    }
    local_kaznu_award_xp($userid, $xp, 'quiz_pass', $badge);

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
 */
function local_kaznu_confirm_url(): moodle_url {
    return new moodle_url('/local/kaznu/pay.php', [
        'action' => 'confirm',
        'token' => local_kaznu_payment_token(),
    ]);
}

/**
 * Make catalogue courses visible for the demo shelf.
 */
function local_kaznu_ensure_catalogue_visible(): void {
    global $DB;
    $DB->execute("UPDATE {course} SET visible = 1 WHERE id > 1 AND visible = 0");
}
