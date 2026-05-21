<?php
defined('MOODLE_INTERNAL') || die();

/** Demo course shortname. */
const LOCAL_KAZNU_COURSE_SHORTNAME = 'SUMMER2026';

/**
 * Demo course record or false.
 */
function local_kaznu_get_demo_course(): ?stdClass {
    global $DB;
    return $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]) ?: null;
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
    $context = context_course::instance($course->id);
    return is_enrolled($context, $userid);
}

/**
 * Enrol user into the demo course (manual enrolment).
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
    return true;
}

/**
 * Write pass-grade completion records for quiz CMs (fixes module unlock).
 */
function local_kaznu_sync_quiz_pass_completions(stdClass $course): void {
    global $DB;

    require_once($GLOBALS['CFG']->libdir . '/completionlib.php');
    $completion = new completion_info($course);
    $now = time();

    foreach ($DB->get_records('quiz', ['course' => $course->id]) as $quiz) {
        if (strpos($quiz->name, 'Тест модуля') === false && strpos($quiz->name, 'экзамен') === false) {
            continue;
        }
        $cm = get_coursemodule_from_instance('quiz', $quiz->id, $course->id);
        if (!$cm) {
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
            if ($gg->finalgrade === null || (float) $gg->finalgrade < 60) {
                continue;
            }
            $userid = (int) $gg->userid;
            $state = COMPLETION_COMPLETE_PASS;
            $rec = $DB->get_record('course_modules_completion', [
                'coursemoduleid' => $cm->id,
                'userid' => $userid,
            ]);
            if ($rec) {
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
            $completion->update_state($cm, $state, $userid, true);
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
