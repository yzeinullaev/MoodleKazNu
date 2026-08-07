<?php
// This file is part of Moodle - http://moodle.org/
//
// Post-proctoring exam complete page (normal Farabi UI, no JWT/session secrets).

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once(__DIR__ . '/lib.php');

$cmid = required_param('cmid', PARAM_INT);
$attemptid = optional_param('attempt', 0, PARAM_INT);

$cm = get_coursemodule_from_id('quiz', $cmid, 0, false, MUST_EXIST);
$course = get_course($cm->course);
$quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);

require_login($course, false, $cm);
$context = context_module::instance($cm->id);

\local_proctopus\service\proctoring_session_reconciler::reconcile_user_cm((int) $USER->id, (int) $cm->id);

$PAGE->set_url(new moodle_url('/local/proctopus/complete.php', array_filter([
    'cmid' => $cmid,
    'attempt' => $attemptid ?: null,
])));
$PAGE->set_context($context);
$PAGE->set_cm($cm, $course);
$PAGE->set_title(get_string('complete_title', 'local_proctopus'));
$PAGE->set_heading(format_string($course->fullname));
$PAGE->set_pagelayout('standard');
$PAGE->add_body_class('local-proctopus-complete');
$PAGE->requires->css('/local/proctopus/styles.css');

$attempt = null;
if ($attemptid) {
    $attempt = $DB->get_record('quiz_attempts', [
        'id' => $attemptid,
        'quiz' => $quiz->id,
        'userid' => $USER->id,
    ], '*', IGNORE_MISSING);
}
if (!$attempt) {
    $rows = $DB->get_records_sql(
        "SELECT * FROM {quiz_attempts} WHERE quiz = ? AND userid = ? ORDER BY id DESC",
        [$quiz->id, $USER->id],
        0,
        1
    );
    $attempt = $rows ? reset($rows) : null;
    if ($attempt) {
        $attemptid = (int) $attempt->id;
    }
}

$gradehtml = '';
$reviewurl = null;
$pendingresult = false;
if ($attempt && $attempt->state === 'finished') {
    try {
        require_once($CFG->dirroot . '/mod/quiz/lib.php');
        $options = quiz_get_review_options($quiz, $attempt, $context);
        if (!empty($options->marks) && (int) $options->marks > 0) {
            $grade = quiz_format_grade($quiz, $attempt->sumgrades);
            $max = quiz_format_grade($quiz, $quiz->sumgrades);
            $gradehtml = get_string('complete_grade', 'local_proctopus', (object) [
                'grade' => $grade,
                'max' => $max,
            ]);
        }
        if (!empty($options->attempt)) {
            $reviewurl = new moodle_url('/mod/quiz/review.php', ['attempt' => $attempt->id, 'cmid' => $cmid]);
        }
    } catch (\Throwable $e) {
        // ok without grade
    }
    $pendingresult = $DB->record_exists('local_proctopus_session', [
        'userid' => $USER->id,
        'cmid' => $cmid,
        'attemptid' => $attempt->id,
        'status' => 'completed_pending_result',
    ]);
}

$courseurl = new moodle_url('/course/view.php', ['id' => $course->id]);
$dashurl = new moodle_url('/my/');

echo $OUTPUT->header();
echo local_proctopus_render_stepper('done');
echo html_writer::start_div('local-proctopus-complete-panel fps-complete');
echo html_writer::div(
    html_writer::tag('i', '', [
        'class' => 'icon fa fa-check-circle fa-fw fps-complete-icon',
        'aria-hidden' => 'true',
    ]),
    'fps-complete-icon-wrap',
    ['aria-hidden' => 'true']
);
echo html_writer::tag('h2', get_string('complete_heading', 'local_proctopus'), [
    'class' => 'local-proctopus-complete-heading',
]);
echo html_writer::start_tag('ul', ['class' => 'local-proctopus-complete-checks']);
echo html_writer::tag('li',
    html_writer::tag('span', '✓', ['class' => 'fps-check-mark', 'aria-hidden' => 'true'])
        . ' ' . get_string('complete_answers_sent', 'local_proctopus'));
echo html_writer::tag('li',
    html_writer::tag('span', '✓', ['class' => 'fps-check-mark', 'aria-hidden' => 'true'])
        . ' ' . get_string('complete_attempt_saved', 'local_proctopus'));
echo html_writer::tag('li',
    html_writer::tag('span', '✓', ['class' => 'fps-check-mark', 'aria-hidden' => 'true'])
        . ' ' . get_string('complete_proctoring_ended', 'local_proctopus'));
echo html_writer::end_tag('ul');
if ($pendingresult) {
    echo html_writer::div(
        get_string('complete_pending_provider', 'local_proctopus'),
        'fps-complete-pending',
        ['role' => 'status']
    );
}
if ($gradehtml !== '') {
    echo html_writer::div($gradehtml, 'local-proctopus-complete-grade');
}
echo html_writer::start_div('local-proctopus-complete-actions fps-actions');
echo html_writer::link($courseurl, get_string('complete_back_course', 'local_proctopus'), [
    'class' => 'btn btn-primary fps-cta',
]);
echo html_writer::link($dashurl, get_string('complete_back_dashboard', 'local_proctopus'), [
    'class' => 'btn btn-secondary',
]);
if ($reviewurl) {
    echo html_writer::link($reviewurl, get_string('complete_review', 'local_proctopus'), [
        'class' => 'btn btn-link',
    ]);
}
echo html_writer::end_div();
echo html_writer::end_div();
echo $OUTPUT->footer();
