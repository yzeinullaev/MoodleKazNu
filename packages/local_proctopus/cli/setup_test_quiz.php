<?php
/**
 * Create PROCTOPUS TEST hidden course + Proctoring Test quiz (2–3 questions).
 * Attaches quizaccess_proctopus row ONLY for this quiz with proctopusrequired=1.
 * Does NOT set proctopus_enabled site-wide (must remain 0).
 *
 * php local/proctopus/cli/setup_test_quiz.php
 * php local/proctopus/cli/setup_test_quiz.php --reset
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/mod/quiz/locallib.php');
require_once($CFG->dirroot . '/question/editlib.php');
require_once($CFG->libdir . '/questionlib.php');

list($options, $unrecognized) = cli_get_params(
    ['reset' => false, 'help' => false],
    ['r' => 'reset', 'h' => 'help']
);

if (!empty($options['help'])) {
    cli_writeln("Create hidden PROCTOPUS TEST course and Proctoring Test quiz.");
    cli_writeln("  --reset  Delete existing shortname PROCTOPUS_TEST and recreate");
    exit(0);
}

$admin = get_admin();
\core\session\manager::set_user($admin);

const COURSE_SHORT = 'PROCTOPUS_TEST';
const COURSE_FULL = 'PROCTOPUS TEST';
const QUIZ_NAME = 'Proctoring Test';
const STUDENT_USER = 'proctopus_test_student';
// Override with env PROCTOPUS_TEST_STUDENT_PASS when regenerating credentials.
const STUDENT_PASS = getenv('PROCTOPUS_TEST_STUDENT_PASS') ?: 'ChangeMe-TestStudent-LocalOnly';

function ptt_ensure_user(string $username, string $password, string $firstname, string $lastname, string $email): stdClass {
    global $CFG;
    if ($user = core_user::get_user_by_username($username)) {
        // Do not reset password on every run if user exists (preserve ops changes).
        cli_writeln("User exists: {$username} id={$user->id}");
        return $user;
    }
    $user = (object) [
        'username' => $username,
        'password' => $password,
        'firstname' => $firstname,
        'lastname' => $lastname,
        'email' => $email,
        'auth' => 'manual',
        'confirmed' => 1,
        'mnethostid' => $CFG->mnet_localhost_id,
    ];
    $user->id = user_create_user($user, false, false);
    update_internal_user_password($user, $password);
    cli_writeln("Created user: {$username} id={$user->id}");
    return $user;
}

function ptt_enrol(stdClass $course, stdClass $user, string $roleshort): void {
    global $DB;
    $plugin = enrol_get_plugin('manual');
    if (!$plugin) {
        throw new moodle_exception('manualenroldisabled');
    }
    $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', IGNORE_MISSING);
    if (!$instance) {
        $plugin->add_instance($course);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual'], '*', MUST_EXIST);
    }
    $role = $DB->get_record('role', ['shortname' => $roleshort], '*', MUST_EXIST);
    $plugin->enrol_user($instance, $user->id, $role->id);
    cli_writeln("Enrolled {$user->username} as {$roleshort}");
}

function ptt_get_qcat(stdClass $course): stdClass {
    $ctx = context_course::instance($course->id);
    $cat = question_get_default_category($ctx->id);
    if (!$cat) {
        // Moodle 4.x: ensure top + default.
        question_get_top_category($ctx->id, true);
        $cat = question_get_default_category($ctx->id);
    }
    if (!$cat) {
        throw new moodle_exception('cannotcreatecategory');
    }
    return $cat;
}

function ptt_add_truefalse(stdClass $course, stdClass $cat, string $name, string $text, bool $correcttrue): int {
    $fromform = (object) [
        'category' => "{$cat->id},{$cat->contextid}",
        'name' => $name,
        'questiontext' => ['text' => $text, 'format' => FORMAT_HTML],
        'generalfeedback' => ['text' => '', 'format' => FORMAT_HTML],
        'defaultmark' => 1,
        'penalty' => 0,
        'correctanswer' => $correcttrue ? 1 : 0,
        'feedbacktrue' => ['text' => '', 'format' => FORMAT_HTML],
        'feedbackfalse' => ['text' => '', 'format' => FORMAT_HTML],
        'status' => \core_question\local\bank\question_version_status::QUESTION_STATUS_READY,
    ];
    $qtype = question_bank::get_qtype('truefalse');
    $question = $qtype->save_question(new stdClass(), $fromform);
    cli_writeln("  question: {$name} id={$question->id}");
    return (int) $question->id;
}

// --- main ---
$enabled = (int) get_config('local_proctopus', 'proctopus_enabled');
$live = (int) get_config('local_proctopus', 'proctopus_live_calls_enabled');
cli_writeln("precheck proctopus_enabled={$enabled} live_calls={$live}");
if ($enabled !== 0) {
    cli_error('Refuse: proctopus_enabled must be 0 for this setup run');
}
if ($live !== 0) {
    cli_error('Refuse: proctopus_live_calls_enabled must be 0 for this setup run');
}

$existing = $DB->get_record('course', ['shortname' => COURSE_SHORT]);
if ($existing && empty($options['reset'])) {
    cli_writeln('Course already exists id=' . $existing->id . ' — use --reset to recreate');
    $course = get_course($existing->id);
} else {
    if ($existing) {
        cli_writeln('Deleting course id=' . $existing->id);
        delete_course($existing, false);
    }
    // Prefer miscellaneous / first non-system category.
    $catid = (int) $DB->get_field_sql(
        "SELECT id FROM {course_categories} WHERE id > 0 ORDER BY depth ASC, sortorder ASC, id ASC",
        [],
        IGNORE_MULTIPLE
    );
    if ($catid < 1) {
        $catid = 1;
    }
    $data = new stdClass();
    $data->fullname = COURSE_FULL;
    $data->shortname = COURSE_SHORT;
    $data->category = $catid;
    $data->visible = 0; // hidden
    $data->summary = 'Isolated Proctopus stub test course. Do not enable site-wide proctoring.';
    $data->summaryformat = FORMAT_HTML;
    $data->format = 'topics';
    $data->numsections = 1;
    $course = create_course($data);
    cli_writeln('Created course id=' . $course->id . ' visible=0 category=' . $catid);
}

// Ensure category 1 ok — if failed use first visible cat.
if (empty($course->id)) {
    cli_error('Course create failed');
}

$student = ptt_ensure_user(STUDENT_USER, STUDENT_PASS, 'Proctopus', 'TestStudent', 'proctopus.test@farabischool.local');
$adminuser = $admin;
ptt_enrol($course, $adminuser, 'editingteacher');
ptt_enrol($course, $adminuser, 'student'); // admin may also attempt
ptt_enrol($course, $student, 'student');

// Existing quiz?
$quizmod = $DB->get_record_sql(
    "SELECT cm.id AS cmid, q.id AS quizid, q.name
       FROM {course_modules} cm
       JOIN {modules} m ON m.id = cm.module AND m.name = 'quiz'
       JOIN {quiz} q ON q.id = cm.instance
      WHERE cm.course = ? AND q.name = ?",
    [$course->id, QUIZ_NAME]
);

if ($quizmod && empty($options['reset'])) {
    $quizid = (int) $quizmod->quizid;
    $cmid = (int) $quizmod->cmid;
    cli_writeln("Quiz exists quizid={$quizid} cmid={$cmid}");
} else {
    // Delete old quiz if reset
    if ($quizmod) {
        course_delete_module((int) $quizmod->cmid);
    }
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = 0;
    $moduleinfo->name = QUIZ_NAME;
    $moduleinfo->intro = 'Stub proctoring path test. Site proctopus_enabled remains OFF until separate APPROVE.';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->quizpassword = '';
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->timelimit = 600; // 10 minutes → JWT duration
    $moduleinfo->grade = 10;
    $moduleinfo->sumgrades = 3;
    $moduleinfo->attempts = 0;
    $moduleinfo->grademethod = QUIZ_GRADEHIGHEST;
    $moduleinfo->questionsperpage = 1;
    $moduleinfo->navmethod = 'free';
    $moduleinfo->shuffleanswers = 1;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->attemptonlast = 0;
    $moduleinfo->showuserpicture = 0;
    $moduleinfo->decimalpoints = 2;
    $moduleinfo->questiondecimalpoints = -1;
    $moduleinfo->showblocks = 0;
    $moduleinfo->completion = COMPLETION_TRACKING_NONE;
    $moduleinfo->overduehandling = 'autosubmit';
    $moduleinfo->graceperiod = 0;
    $moduleinfo->canredoquestions = 0;
    $moduleinfo->browsersecurity = '-';
    $moduleinfo->delay1 = 0;
    $moduleinfo->delay2 = 0;
    // Access rule form fields (saved if plugin installed).
    $moduleinfo->proctopusrequired = 1;
    $moduleinfo->proctopuswebsites = '';
    $moduleinfo->proctopuslookaway = 0;
    $moduleinfo->proctopusmoveaway = 0;
    $moduleinfo->proctopusvoices = 0;
    $moduleinfo->proctopusduration = 0;
    $moduleinfo->proctopusmode = 'online';

    $cm = add_moduleinfo($moduleinfo, $course);
    $cmid = (int) $cm->coursemodule;
    $quizid = (int) $cm->instance;
    cli_writeln("Created quiz quizid={$quizid} cmid={$cmid}");

    // Questions
    $cat = ptt_get_qcat($course);
    $qids = [];
    $qids[] = ptt_add_truefalse($course, $cat, 'PT Q1', '<p>2 + 2 = 4?</p>', true);
    $qids[] = ptt_add_truefalse($course, $cat, 'PT Q2', '<p>The capital of Kazakhstan is Astana (Nur-Sultan historically).</p>', true);
    $qids[] = ptt_add_truefalse($course, $cat, 'PT Q3', '<p>This is a proctoring stub question only.</p>', true);

    $quiz = $DB->get_record('quiz', ['id' => $quizid], '*', MUST_EXIST);
    $quizobj = quiz::create($quizid);
    $quizsettings = $quizobj->get_quiz();
    require_once($CFG->dirroot . '/mod/quiz/locallib.php');
    foreach ($qids as $slot => $qid) {
        quiz_add_quiz_question($qid, $quiz, 0);
    }
    quiz_update_sumgrades($quiz);
    // Rebuild quiz structure.
    \mod_quiz\structure::create_for_quiz($quizobj);
    cli_writeln('Added ' . count($qids) . ' questions');
}

// Ensure access-rule row ONLY for this quiz (even if form save path missed).
if ($DB->get_manager()->table_exists('quizaccess_proctopus')) {
    $DB->delete_records('quizaccess_proctopus', ['quizid' => $quizid]);
    $DB->insert_record('quizaccess_proctopus', (object) [
        'quizid' => $quizid,
        'proctopusrequired' => 1,
        'proctopuswebsites' => '',
        'proctopuslookaway' => 0,
        'proctopusmoveaway' => 0,
        'proctopusvoices' => 0,
        'proctopusduration' => 0,
        'proctopusmode' => 'online',
    ]);
    $other = $DB->count_records_select('quizaccess_proctopus', 'quizid <> ? AND proctopusrequired = 1', [$quizid]);
    cli_writeln("quizaccess_proctopus: this_quiz required=1; other required quizzes={$other}");
} else {
    cli_writeln('WARN: quizaccess_proctopus table missing — plugin not upgraded?');
}

// Final safety: never leave global enable on.
set_config('proctopus_enabled', 0, 'local_proctopus');
set_config('proctopus_live_calls_enabled', 0, 'local_proctopus');

// Counts for report.
$sessions = $DB->get_manager()->table_exists('local_proctopus_session')
    ? $DB->count_records('local_proctopus_session', ['quizid' => $quizid]) : 0;
$attempts = $DB->count_records('quiz_attempts', ['quiz' => $quizid]);

cli_writeln('=== TEST QUIZ CREATED ===');
cli_writeln('course_id=' . $course->id);
cli_writeln('quiz_id=' . $quizid);
cli_writeln('cmid=' . $cmid);
cli_writeln('rule_attached=1');
cli_writeln('proctopus_enabled=' . (int) get_config('local_proctopus', 'proctopus_enabled'));
cli_writeln('live_calls=' . (int) get_config('local_proctopus', 'proctopus_live_calls_enabled'));
cli_writeln('sessions_for_quiz=' . $sessions);
cli_writeln('attempts_for_quiz=' . $attempts);
cli_writeln('student=' . STUDENT_USER);
cli_writeln('student_password_note=set_on_create_only_see_env_or_constant');
cli_writeln('course_url=' . (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false));
cli_writeln('quiz_url=' . (new moodle_url('/mod/quiz/view.php', ['id' => $cmid]))->out(false));
