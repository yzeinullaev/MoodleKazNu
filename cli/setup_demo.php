<?php
// This file is part of Moodle - http://moodle.org/
//
// CLI: create demo summer school course, student, and final exam.

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->dirroot . '/course/lib.php');
require_once($CFG->dirroot . '/course/modlib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once($CFG->libdir . '/enrollib.php');
require_once($CFG->dirroot . '/mod/quiz/lib.php');
require_once($CFG->dirroot . '/question/type/multichoice/questiontype.php');

list($options, $unrecognized) = cli_get_params(
    [
        'reset' => false,
        'help' => false,
    ],
    [
        'r' => 'reset',
        'h' => 'help',
    ]
);

// Run as admin so question bank APIs have valid context/capabilities.
$admin = get_admin();
\core\session\manager::set_user($admin);

if ($options['help']) {
    echo "Create KazNU summer school demo course.\n";
    echo "Options:\n";
    echo "  -r, --reset   Delete and recreate SUMMER2026 course\n";
    echo "  -h, --help    Print this help\n";
    exit(0);
}

const COURSE_SHORTNAME = 'SUMMER2026';
const STUDENT_USER = 'demo_student';
const STUDENT_PASS = 'Student2026!';
const TEACHER_USER = 'demo_teacher';
const TEACHER_PASS = 'Teacher2026!';

/**
 * Ensure a manual-auth user exists.
 */
function local_kaznu_ensure_user(string $username, string $password, string $firstname, string $lastname, string $email): stdClass {
    global $CFG;

    if ($user = core_user::get_user_by_username($username)) {
        cli_writeln("User exists: {$username}");
        update_internal_user_password($user, $password);
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
    cli_writeln("Created user: {$username}");
    return $user;
}

/**
 * Enrol user with role shortname.
 */
function local_kaznu_enrol_user(stdClass $course, int $userid, string $roleshortname = 'student'): void {
    global $DB;

    $role = $DB->get_record('role', ['shortname' => $roleshortname], '*', MUST_EXIST);
    $plugin = enrol_get_plugin('manual');
    $instances = enrol_get_instances($course->id, true);
    $instance = null;

    foreach ($instances as $inst) {
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
        cli_writeln("Already enrolled ({$roleshortname}): user {$userid}");
        return;
    }

    $plugin->enrol_user($instance, $userid, $role->id);
    cli_writeln("Enrolled as {$roleshortname}: user {$userid}");
}

/**
 * Add a Page activity to a section.
 */
function local_kaznu_add_page(stdClass $course, int $sectionnum, string $name, string $html): int {
    global $DB;

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'page';
    $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'page']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->name = $name;
    $moduleinfo->intro = '';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->content = $html;
    $moduleinfo->contentformat = FORMAT_HTML;
    $moduleinfo->completion = COMPLETION_TRACKING_NONE;

    $cm = add_moduleinfo($moduleinfo, $course);
    cli_writeln("Added page: {$name} (cm {$cm->coursemodule})");
    return (int) $cm->coursemodule;
}

/**
 * Create one multichoice question in the course bank.
 */
function local_kaznu_create_mc_question(stdClass $category, string $name, string $text, array $answers): int {
    $qtype = question_bank::get_qtype('multichoice');

    $question = new stdClass();
    $question->qtype = 'multichoice';

    $form = new stdClass();
    $form->category = $category->id . ',' . $category->contextid;
    $form->name = $name;
    $form->questiontext = ['text' => $text, 'format' => FORMAT_HTML];
    $form->generalfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->defaultmark = 1;
    $form->penalty = 0.3333333;
    $form->length = 1;
    $form->single = 1;
    $form->shuffleanswers = 1;
    $form->answernumbering = 'abc';
    $form->showstandardinstruction = 0;
    $form->correctfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->partiallycorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->incorrectfeedback = ['text' => '', 'format' => FORMAT_HTML];
    $form->shownumcorrect = 0;
    $form->answer = [];
    $form->fraction = [];
    $form->feedback = [];

    foreach ($answers as $answer) {
        $form->answer[] = ['text' => $answer['text'], 'format' => FORMAT_HTML];
        $form->fraction[] = $answer['fraction'];
        $form->feedback[] = ['text' => '', 'format' => FORMAT_HTML];
    }

    $saved = $qtype->save_question($question, $form);
    return (int) $saved->id;
}

/**
 * Add quiz and exam questions.
 */
function local_kaznu_add_exam(stdClass $course, int $sectionnum): void {
    global $DB;

    $modname = 'Итоговый экзамен';
    $existing = $DB->get_record('quiz', ['course' => $course->id, 'name' => $modname]);
    if ($existing) {
        cli_writeln('Exam quiz already exists.');
        return;
    }

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->name = $modname;
    $moduleinfo->intro = '<p>Ответьте на все вопросы. Для сдачи нужно набрать <strong>60%</strong> и более.</p>';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->grade = 100;
    $moduleinfo->sumgrades = 5;
    $moduleinfo->attempts = 2;
    $moduleinfo->grademethod = 1;
    $moduleinfo->timelimit = 1200;
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->quizpassword = '';
    $moduleinfo->completion = COMPLETION_TRACKING_NONE;

    $cm = add_moduleinfo($moduleinfo, $course);
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    cli_writeln("Added quiz: {$modname} (quiz {$quiz->id})");

    context_helper::reset_caches();
    $coursecontext = context_course::instance($course->id, MUST_EXIST);
    $top = question_get_top_category($coursecontext->id, true);
    $cat = question_get_default_category($coursecontext->id);
    if (!$cat) {
        $cat = (object) [
            'name' => shorten_text(get_string('defaultfor', 'question', $coursecontext->get_context_name(false, true)), 255),
            'info' => get_string('defaultinfofor', 'question', $coursecontext->get_context_name(false, true)),
            'contextid' => $coursecontext->id,
            'parent' => $top->id,
            'sortorder' => 999,
            'stamp' => make_unique_id_code(),
        ];
        $cat->id = $DB->insert_record('question_categories', $cat);
    }

    $bank = [
        [
            'name' => 'Летняя школа — вопрос 1',
            'text' => '<p>Что такое онлайн-обучение?</p>',
            'answers' => [
                ['text' => 'Обучение через интернет с использованием цифровых платформ', 'fraction' => 1],
                ['text' => 'Только очные лекции в аудитории', 'fraction' => 0],
                ['text' => 'Обучение без преподавателя и без заданий', 'fraction' => 0],
                ['text' => 'Только просмотр видео без обратной связи', 'fraction' => 0],
            ],
        ],
        [
            'name' => 'Летняя школа — вопрос 2',
            'text' => '<p>Какой формат подходит для летней онлайн-школы?</p>',
            'answers' => [
                ['text' => 'Сочетание видеоуроков, заданий и онлайн-консультаций', 'fraction' => 1],
                ['text' => 'Только письменные экзамены в классе', 'fraction' => 0],
                ['text' => 'Только самостоятельное чтение без дедлайнов', 'fraction' => 0],
                ['text' => 'Только устные ответы по телефону', 'fraction' => 0],
            ],
        ],
        [
            'name' => 'Летняя школа — вопрос 3',
            'text' => '<p>Зачем нужен итоговый экзамен в курсе?</p>',
            'answers' => [
                ['text' => 'Проверить усвоение материала и зафиксировать результат', 'fraction' => 1],
                ['text' => 'Увеличить нагрузку без цели', 'fraction' => 0],
                ['text' => 'Заменить все занятия одним тестом', 'fraction' => 0],
                ['text' => 'Скрыть оценки от студента', 'fraction' => 0],
            ],
        ],
        [
            'name' => 'Летняя школа — вопрос 4',
            'text' => '<p>Что помогает успешно пройти онлайн-курс?</p>',
            'answers' => [
                ['text' => 'Регулярная работа по плану и выполнение заданий в срок', 'fraction' => 1],
                ['text' => 'Редкие заходы на платформу раз в месяц', 'fraction' => 0],
                ['text' => 'Игнорирование обратной связи преподавателя', 'fraction' => 0],
                ['text' => 'Отсутствие заметок и конспекта', 'fraction' => 0],
            ],
        ],
        [
            'name' => 'Летняя школа — вопрос 5',
            'text' => '<p>Какой минимальный порог для сдачи демо-экзамена?</p>',
            'answers' => [
                ['text' => '60%', 'fraction' => 1],
                ['text' => '40%', 'fraction' => 0],
                ['text' => '90%', 'fraction' => 0],
                ['text' => '100% только без ошибок', 'fraction' => 0],
            ],
        ],
    ];

    $slot = 1;
    foreach ($bank as $item) {
        $qid = local_kaznu_create_mc_question($cat, $item['name'], $item['text'], $item['answers']);
        quiz_add_quiz_question($qid, $quiz, $slot);
        $slot++;
    }

    quiz_update_sumgrades($quiz);
    $DB->set_field('quiz', 'grade', 100, ['id' => $quiz->id]);
    cli_writeln('Added ' . count($bank) . ' exam questions.');
}

// --- Main ---

if ($options['reset']) {
    if ($old = $DB->get_record('course', ['shortname' => COURSE_SHORTNAME])) {
        delete_course($old, false);
        fix_course_sortorder();
        cli_writeln('Removed old demo course.');
    }
}

$course = $DB->get_record('course', ['shortname' => COURSE_SHORTNAME]);

if (!$course) {
    $data = (object) [
        'category' => 1,
        'fullname' => 'Летняя онлайн-школа КазНУ (демо)',
        'shortname' => COURSE_SHORTNAME,
        'summary' => '<p>Демонстрационный курс для онлайн летней школы. Изучите материалы и сдайте итоговый экзамен.</p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 3,
        'visible' => 1,
        'enablecompletion' => 1,
        'showgrades' => 1,
        'lang' => 'ru',
    ];
    $course = create_course($data);
    cli_writeln("Created course id {$course->id}");
} else {
    cli_writeln("Using existing course id {$course->id}");
}

$hero = '
<div class="local-kaznu-hero">
  <span class="local-kaznu-badge">КазНУ · Летняя онлайн-школа</span>
  <h2>Добро пожаловать на демо-курс</h2>
  <p>Пройдите три шага: ознакомление → модуль → итоговый экзамен.</p>
</div>
<div class="local-kaznu-steps">
  <div class="local-kaznu-step"><strong>Шаг 1</strong><br>Ознакомьтесь с программой</div>
  <div class="local-kaznu-step"><strong>Шаг 2</strong><br>Изучите учебный модуль</div>
  <div class="local-kaznu-step"><strong>Шаг 3</strong><br>Сдайте экзамен (60% для зачёта)</div>
</div>
';

$module1 = '
<div class="local-kaznu-hero" style="padding:1.25rem 1.5rem;">
  <h2 style="font-size:1.35rem;">Модуль 1. Основы онлайн-обучения</h2>
</div>
<h3>Темы модуля</h3>
<ul>
  <li>Платформа Moodle и навигация по курсу</li>
  <li>Видеолекции и материалы для самостоятельной работы</li>
  <li>Обратная связь с преподавателем</li>
</ul>
<p><strong>Задание:</strong> прочитайте материалы и переходите к итоговому экзамену в следующем разделе.</p>
';

// Section 0 is general; topics format uses section 1..n.
local_kaznu_add_page($course, 1, 'Добро пожаловать', $hero);
local_kaznu_add_page($course, 2, 'Модуль 1 — Основы', $module1);
local_kaznu_add_exam($course, 3);

$student = local_kaznu_ensure_user(STUDENT_USER, STUDENT_PASS, 'Айдар', 'Студент', 'demo.student@moodle.local');
$teacher = local_kaznu_ensure_user(TEACHER_USER, TEACHER_PASS, 'Алия', 'Преподаватель', 'demo.teacher@moodle.local');

local_kaznu_enrol_user($course, (int) $student->id, 'student');
local_kaznu_enrol_user($course, (int) $teacher->id, 'editingteacher');

// Set course pass grade display on quiz via rebuild.
rebuild_course_cache($course->id);
purge_all_caches();

cli_writeln('');
cli_writeln('=== Demo ready ===');
cli_writeln('Course URL: ' . $CFG->wwwroot . '/course/view.php?id=' . $course->id);
cli_writeln('Student login: ' . STUDENT_USER . ' / ' . STUDENT_PASS);
cli_writeln('Teacher login: ' . TEACHER_USER . ' / ' . TEACHER_PASS);
cli_writeln('Admin login: admin (existing)');
