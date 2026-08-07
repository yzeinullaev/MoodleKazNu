<?php
/**
 * Add 3 truefalse questions to Proctoring Test quiz (isolated repair).
 * php local/proctopus/cli/add_test_quiz_questions.php --quizid=22
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');

list($options, $unrecognized) = cli_get_params(['quizid' => 0, 'help' => false], ['h' => 'help']);
if (!empty($options['help']) || empty($options['quizid'])) {
    cli_writeln('php add_test_quiz_questions.php --quizid=N');
    exit(empty($options['quizid']) ? 1 : 0);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

$quizid = (int) $options['quizid'];
$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = get_course($quiz->course);
$cm = get_coursemodule_from_instance('quiz', $quizid, $course->id, false, MUST_EXIST);

// Use module context category (Moodle 4 question bank).
$modcontext = context_module::instance($cm->id);
$coursecontext = context_course::instance($course->id);

// Ensure categories exist in both contexts; prefer activity category.
$contexts = [$modcontext, $coursecontext];
$cat = null;
foreach ($contexts as $ctx) {
    question_get_top_category($ctx->id, true);
    $cand = question_get_default_category($ctx->id);
    if ($cand) {
        $cat = $cand;
        cli_writeln("Using category id={$cat->id} contextid={$cat->contextid}");
        break;
    }
}
if (!$cat) {
    // Manual category insert under top for course context.
    $top = question_get_top_category($coursecontext->id, true);
    $cat = (object) [
        'name' => 'Default for PROCTOPUS TEST',
        'contextid' => $coursecontext->id,
        'info' => '',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'parent' => $top->id,
        'sortorder' => 999,
        'idnumber' => null,
    ];
    $cat->id = $DB->insert_record('question_categories', $cat);
    cli_writeln("Created category id={$cat->id}");
}

$items = [
    ['PT Q1', '<p>2 + 2 = 4?</p>', true],
    ['PT Q2', '<p>Astana is the capital of Kazakhstan?</p>', true],
    ['PT Q3', '<p>This is a proctoring stub question only?</p>', true],
];

// If slots already exist, skip recreate.
$existslots = $DB->count_records('quiz_slots', ['quizid' => $quizid]);
if ($existslots > 0) {
    cli_writeln("Quiz already has {$existslots} slots — leaving questions as-is");
    exit(0);
}

$qtype = question_bank::get_qtype('truefalse');
$qids = [];
foreach ($items as [$name, $text, $correct]) {
    $fromform = (object) [
        'category' => $cat->id . ',' . $cat->contextid,
        'name' => $name,
        'questiontext' => ['text' => $text, 'format' => FORMAT_HTML],
        'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
        'defaultmark' => 1.0,
        'penalty' => 0,
        'correctanswer' => $correct ? 1 : 0,
        'feedbacktrue' => ['text' => '', 'format' => FORMAT_HTML],
        'feedbackfalse' => ['text' => '', 'format' => FORMAT_HTML],
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
        'idnumber' => '',
        'tags' => [],
    ];
    try {
        $question = $qtype->save_question(new stdClass(), $fromform);
    } catch (Throwable $e) {
        cli_writeln('save_question failed: ' . $e->getMessage());
        // Fallback: simpler form shapes for older path.
        $fromform->questiontext = $text;
        $fromform->questiontextformat = FORMAT_HTML;
        $fromform->generalfeedback = '';
        $fromform->generalfeedbackformat = FORMAT_HTML;
        $fromform->feedbacktrue = '';
        $fromform->feedbacktrueformat = FORMAT_HTML;
        $fromform->feedbackfalse = '';
        $fromform->feedbackfalseformat = FORMAT_HTML;
        $question = $qtype->save_question(new stdClass(), $fromform);
    }
    $qids[] = (int) $question->id;
    cli_writeln("created question id={$question->id} name={$name}");
    quiz_add_quiz_question((int) $question->id, $quiz, 0);
}

quiz_update_sumgrades($quiz);
// Try rebuild structure if available.
try {
    $quizobj = quiz::create($quizid);
    if (class_exists('\\mod_quiz\\structure')) {
        \mod_quiz\structure::create_for_quiz($quizobj);
    }
} catch (Throwable $e) {
    cli_writeln('structure note: ' . $e->getMessage());
}

$slots = $DB->count_records('quiz_slots', ['quizid' => $quizid]);
cli_writeln("slots={$slots}");
cli_writeln('DONE');
