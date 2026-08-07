<?php
/**
 * Rebuild working truefalse questions for quiz (fix empty qtype).
 * php local/proctopus/cli/rebuild_test_quiz_questions.php --quizid=22
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/questionlib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/type/truefalse/questiontype.php');

list($options) = cli_get_params(['quizid' => 22], []);
$quizid = (int) $options['quizid'];
$admin = get_admin();
\core\session\manager::set_user($admin);

$quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
$course = get_course($quiz->course);
$cm = get_coursemodule_from_instance('quiz', $quizid, $course->id, false, MUST_EXIST);
$quizobj = \mod_quiz\quiz_settings::create($quizid);

// Clear existing slots via structure API if possible.
$structure = \mod_quiz\structure::create_for_quiz($quizobj);
foreach ($structure->get_slots() as $slot) {
    $structure->remove_slot($slot->slot);
}
cli_writeln('slots cleared');

$coursecontext = context_course::instance($course->id);
question_get_top_category($coursecontext->id, true);
$cat = question_get_default_category($coursecontext->id);
if (!$cat) {
    $top = $DB->get_record('question_categories', ['contextid' => $coursecontext->id, 'parent' => 0], '*', MUST_EXIST);
    $cat = (object) [
        'name' => 'PROCTOPUS TEST',
        'contextid' => $coursecontext->id,
        'info' => '',
        'infoformat' => FORMAT_HTML,
        'stamp' => make_unique_id_code(),
        'parent' => $top->id,
        'sortorder' => 999,
        'idnumber' => null,
    ];
    $cat->id = $DB->insert_record('question_categories', $cat);
}

$qtype = question_bank::get_qtype('truefalse');
$items = [
    ['E2E Q1', '<p>2 + 2 = 4?</p>', 1],
    ['E2E Q2', '<p>Astana is the capital of Kazakhstan?</p>', 1],
    ['E2E Q3', '<p>Stub proctoring question?</p>', 1],
];

foreach ($items as [$name, $text, $correct]) {
    // Build form like question bank export expects.
    $form = new stdClass();
    $form->category = $cat->id . ',' . $cat->contextid;
    $form->name = $name;
    $form->questiontext = ['text' => $text, 'format' => FORMAT_HTML];
    $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->defaultmark = 1.0;
    $form->penalty = 0;
    $form->correctanswer = $correct;
    $form->feedbacktrue = ['text' => 'OK', 'format' => FORMAT_HTML];
    $form->feedbackfalse = ['text' => 'Wrong', 'format' => FORMAT_HTML];
    $form->status = \core_question\local\bank\question_version_status::QUESTION_STATUS_READY;
    $form->idnumber = '';
    $form->shownumcorrect = 0;

    $question = new stdClass();
    $question->qtype = 'truefalse';
    $saved = $qtype->save_question($question, $form);
    if (empty($saved->id) || empty($saved->qtype)) {
        // Force qtype field if save left it empty (edge bug).
        $DB->set_field('question', 'qtype', 'truefalse', ['id' => $saved->id]);
        $saved = $DB->get_record('question', ['id' => $saved->id], '*', MUST_EXIST);
    }
    cli_writeln("q id={$saved->id} qtype={$saved->qtype} name={$name}");
    quiz_add_quiz_question((int) $saved->id, $quiz, 0);
}

quiz_update_sumgrades($quiz);
// Re-fetch types.
foreach ($DB->get_records_sql(
    "SELECT q.id, q.qtype, q.name
       FROM {quiz_slots} qs
       JOIN {question_references} qr ON qr.itemid = qs.id
            AND qr.component = 'mod_quiz' AND qr.questionarea = 'slot'
       JOIN {question_bank_entries} qbe ON qbe.id = qr.questionbankentryid
       JOIN {question_versions} qv ON qv.questionbankentryid = qbe.id
            AND qv.version = (
                SELECT MAX(v2.version) FROM {question_versions} v2 WHERE v2.questionbankentryid = qbe.id
            )
       JOIN {question} q ON q.id = qv.questionid
      WHERE qs.quizid = ?",
    [$quizid]
) as $r) {
    cli_writeln("slot question {$r->id} type={$r->qtype}");
    if ($r->qtype === '' || $r->qtype === 'missingtype') {
        cli_error('Still broken qtype for ' . $r->id);
    }
}
cli_writeln('REBUILD_OK');
