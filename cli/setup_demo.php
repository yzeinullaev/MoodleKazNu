<?php
// CLI: create demo summer school course with modules, media, tests, and payment flow.

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
require_once(__DIR__ . '/../locallib.php');

list($options, $unrecognized) = cli_get_params(
    ['reset' => false, 'help' => false],
    ['r' => 'reset', 'h' => 'help']
);

$admin = get_admin();
\core\session\manager::set_user($admin);

if ($options['help']) {
    echo "Create KazNU summer school demo course.\n";
    echo "  -r, --reset   Delete and recreate SUMMER2026\n";
    exit(0);
}

const TEACHER_USER = 'demo_teacher';
const TEACHER_PASS = 'Teacher2026!';
const STUDENT_USER = 'demo_student';
const STUDENT_PASS = 'Student2026!';

function local_kaznu_cli_ensure_user(string $username, string $password, string $firstname, string $lastname, string $email): stdClass {
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

function local_kaznu_cli_set_section_name(stdClass $course, int $sectionnum, string $name): void {
    global $DB;
    $section = $DB->get_record('course_sections', ['course' => $course->id, 'section' => $sectionnum], '*', MUST_EXIST);
    $section->name = $name;
    $section->summary = '';
    $section->summaryformat = FORMAT_HTML;
    $DB->update_record('course_sections', $section);
}

function local_kaznu_cli_add_label(stdClass $course, int $sectionnum, string $text, ?string $availability = null): int {
    global $DB;
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'label';
    $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'label']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->name = '';
    $moduleinfo->intro = $text;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    if ($availability) {
        $moduleinfo->availability = $availability;
    }
    $cm = add_moduleinfo($moduleinfo, $course);
    return (int) $cm->coursemodule;
}

function local_kaznu_cli_add_page(stdClass $course, int $sectionnum, string $name, string $html, ?string $availability = null): int {
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
    if ($availability) {
        $moduleinfo->availability = $availability;
    }
    $cm = add_moduleinfo($moduleinfo, $course);
    cli_writeln("  page: {$name}");
    return (int) $cm->coursemodule;
}

function local_kaznu_cli_add_url(stdClass $course, int $sectionnum, string $name, string $url, ?string $availability = null): int {
    global $DB;
    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'url';
    $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'url']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->name = $name;
    $moduleinfo->intro = '';
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->externalurl = $url;
    $moduleinfo->display = 0;
    $moduleinfo->displayoptions = 'a:1:{s:10:"printintro";i:0;}';
    if ($availability) {
        $moduleinfo->availability = $availability;
    }
    $cm = add_moduleinfo($moduleinfo, $course);
    cli_writeln("  url: {$name}");
    return (int) $cm->coursemodule;
}

function local_kaznu_cli_completion_availability(int $cmid): string {
    return json_encode([
        'op' => '&',
        'c' => [['type' => 'completion', 'cm' => $cmid, 'e' => 2]],
        'showc' => [true],
    ]);
}

function local_kaznu_cli_get_question_category(stdClass $course): stdClass {
    global $DB;
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
    return $cat;
}

function local_kaznu_cli_create_mc_question(stdClass $category, string $name, string $text, array $answers): int {
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
 * @return array{quiz: stdClass, gradeitemid: int}
 */
function local_kaznu_cli_add_quiz(
    stdClass $course,
    int $sectionnum,
    string $name,
    string $intro,
    array $questions,
    stdClass $category,
    ?string $availability = null,
    int $timelimit = 600
): array {
    global $DB;

    $moduleinfo = new stdClass();
    $moduleinfo->modulename = 'quiz';
    $moduleinfo->module = (int) $DB->get_field('modules', 'id', ['name' => 'quiz']);
    $moduleinfo->course = $course->id;
    $moduleinfo->section = $sectionnum;
    $moduleinfo->name = $name;
    $moduleinfo->intro = $intro;
    $moduleinfo->introformat = FORMAT_HTML;
    $moduleinfo->visible = 1;
    $moduleinfo->grade = 100;
    $moduleinfo->sumgrades = count($questions);
    $moduleinfo->attempts = 3;
    $moduleinfo->grademethod = 1;
    $moduleinfo->timelimit = $timelimit;
    $moduleinfo->timeopen = 0;
    $moduleinfo->timeclose = 0;
    $moduleinfo->quizpassword = '';
    $moduleinfo->completion = COMPLETION_TRACKING_AUTOMATIC;
    $moduleinfo->completionusegrade = 1;
    $moduleinfo->completionpassgrade = 1;
    $moduleinfo->completiongradeitemnumber = 0;
    $moduleinfo->preferredbehaviour = 'deferredfeedback';
    $moduleinfo->questionsperpage = 1;
    $moduleinfo->navmethod = 'free';
    if ($availability) {
        $moduleinfo->availability = $availability;
    }

    $cm = add_moduleinfo($moduleinfo, $course);
    $quiz = $DB->get_record('quiz', ['id' => $cm->instance], '*', MUST_EXIST);
    cli_writeln("  quiz: {$name} (id {$quiz->id})");

    $slot = 1;
    foreach ($questions as $item) {
        $qid = local_kaznu_cli_create_mc_question($category, $item['name'], $item['text'], $item['answers']);
        quiz_add_quiz_question($qid, $quiz, $slot);
        $slot++;
    }
    quiz_update_sumgrades($quiz);
    $DB->set_field('quiz', 'grade', 100, ['id' => $quiz->id]);
    local_kaznu_apply_quiz_review_settings((int) $quiz->id);

    $gradeitem = $DB->get_record('grade_items', [
        'courseid' => $course->id,
        'itemtype' => 'mod',
        'itemmodule' => 'quiz',
        'iteminstance' => $quiz->id,
    ], '*', MUST_EXIST);

    return [
        'quiz' => $quiz,
        'gradeitemid' => (int) $gradeitem->id,
        'cmid' => (int) $cm->coursemodule,
    ];
}

// --- Main ---

if ($options['reset']) {
    if ($old = $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME])) {
        delete_course($old, false);
        fix_course_sortorder();
        cli_writeln('Removed old demo course.');
    }
}

set_config('paymenttoken', 'DEMO-KZN-2026', 'local_kaznu');
set_config('paymentprice', '25 000 ₸', 'local_kaznu');

$course = $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]);

if (!$course) {
    $data = (object) [
        'category' => 1,
        'fullname' => 'Летняя онлайн-школа КазНУ (демо)',
        'shortname' => LOCAL_KAZNU_COURSE_SHORTNAME,
        'summary' => '<p>Мультимедийный курс: видео, аудио, тесты по модулям и итоговый экзамен. <a href="/local/kaznu/pay.php">Записаться на курс</a></p>',
        'summaryformat' => FORMAT_HTML,
        'format' => 'topics',
        'numsections' => 5,
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

$cat = local_kaznu_cli_get_question_category($course);
$paylink = $CFG->wwwroot . '/local/kaznu/pay.php';

// --- Section 1: Welcome ---
local_kaznu_cli_set_section_name($course, 1, 'Добро пожаловать');
local_kaznu_cli_add_label($course, 1, '<div class="local-kaznu-section-label"><span class="local-kaznu-badge">Старт</span></div>');
local_kaznu_cli_add_page($course, 1, 'О программе летней школы', '
<div class="local-kaznu-hero">
  <span class="local-kaznu-badge">КазНУ · Летняя онлайн-школа</span>
  <h2>Добро пожаловать!</h2>
  <p>Интерактивный курс с видеолекциями, аудиоматериалами и пошаговыми тестами.</p>
</div>
<div class="local-kaznu-steps">
  <div class="local-kaznu-step"><strong>Модуль 1</strong><br>Видео + аудио + тест</div>
  <div class="local-kaznu-step"><strong>Модуль 2</strong><br>Практика + тест</div>
  <div class="local-kaznu-step"><strong>Модуль 3</strong><br>Итоговые материалы</div>
  <div class="local-kaznu-step"><strong>Экзамен</strong><br>60% для зачёта</div>
</div>
<p class="local-kaznu-cta"><a class="local-kaznu-btn-link" href="' . s($paylink) . '">Записаться на курс (демо-оплата)</a></p>
');

// --- Section 2: Module 1 ---
local_kaznu_cli_set_section_name($course, 2, 'Модуль 1 — Введение в онлайн-обучение');
local_kaznu_cli_add_label($course, 2, '<div class="local-kaznu-section-label"><h3 class="local-kaznu-mod-title">📘 Модуль 1</h3><p>Изучите материалы, затем пройдите контрольный тест (60%).</p></div>');
local_kaznu_cli_add_page($course, 2, 'Тема 1.1 — Что такое онлайн-обучение', '
<div class="local-kaznu-card">
  <h3>Ключевые понятия</h3>
  <ul>
    <li>Смешанное и полностью дистанционное обучение</li>
    <li>Синхронные и асинхронные форматы</li>
    <li>Роль преподавателя и обратной связи</li>
  </ul>
  <p><em>После изучения просмотрите видеолекцию и прослушайте аудиоконспект ниже.</em></p>
</div>
');
local_kaznu_cli_add_url($course, 2, '🎬 Видеолекция: Платформа и навигация', 'https://www.youtube.com/watch?v=8CwoCtBZn9k');
local_kaznu_cli_add_page($course, 2, '🎧 Аудиолекция: Как учиться онлайн', '
<div class="local-kaznu-media local-kaznu-audio">
  <h3>Аудиоматериал модуля 1</h3>
  <p>Краткий обзор эффективных стратегий дистанционного обучения.</p>
  <audio controls preload="metadata" style="width:100%;max-width:520px">
    <source src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-1.mp3" type="audio/mpeg">
    Ваш браузер не поддерживает аудио.
  </audio>
</div>
');

$mod1questions = [
    [
        'name' => 'М1 — Вопрос 1',
        'text' => '<p>Что относится к онлайн-обучению?</p>',
        'answers' => [
            ['text' => 'Обучение через интернет и цифровые платформы', 'fraction' => 1],
            ['text' => 'Только очные лекции без материалов', 'fraction' => 0],
            ['text' => 'Только переписка без заданий', 'fraction' => 0],
            ['text' => 'Только просмотр ТВ', 'fraction' => 0],
        ],
    ],
    [
        'name' => 'М1 — Вопрос 2',
        'text' => '<p>Какой формат типичен для летней онлайн-школы?</p>',
        'answers' => [
            ['text' => 'Видео + задания + консультации', 'fraction' => 1],
            ['text' => 'Только письменный экзамен в классе', 'fraction' => 0],
            ['text' => 'Только чтение без дедлайнов', 'fraction' => 0],
            ['text' => 'Только устные звонки', 'fraction' => 0],
        ],
    ],
];
$mod1 = local_kaznu_cli_add_quiz(
    $course,
    2,
    '✅ Тест модуля 1',
    '<p>Контрольный тест по материалам модуля 1. Для прохождения нужно <strong>60%</strong> и более. Следующий модуль откроется после успешной сдачи.</p>',
    $mod1questions,
    $cat
);
$availaftermod1 = local_kaznu_cli_completion_availability($mod1['cmid']);

// --- Section 3: Module 2 ---
local_kaznu_cli_set_section_name($course, 3, 'Модуль 2 — Методики и практика');
local_kaznu_cli_add_label($course, 3, '<div class="local-kaznu-section-label"><h3 class="local-kaznu-mod-title">📗 Модуль 2</h3><p>Доступен после сдачи теста модуля 1.</p></div>', $availaftermod1);
local_kaznu_cli_add_page($course, 3, 'Тема 2.1 — Методики преподавания онлайн', '
<div class="local-kaznu-card">
  <h3>Практические рекомендации</h3>
  <ol>
    <li>Дробите материал на короткие блоки (15–20 мин)</li>
    <li>Чередуйте видео, текст и интерактив</li>
    <li>Давайте регулярную обратную связь</li>
  </ol>
</div>
', $availaftermod1);
local_kaznu_cli_add_url($course, 3, '🎬 Видеолекция: Обратная связь студентам', 'https://www.youtube.com/watch?v=9bZkp7q19f0', $availaftermod1);
local_kaznu_cli_add_page($course, 3, '🎧 Аудио: Планирование учебной недели', '
<div class="local-kaznu-media local-kaznu-audio">
  <h3>Аудиоматериал модуля 2</h3>
  <audio controls preload="metadata" style="width:100%;max-width:520px">
    <source src="https://www.soundhelix.com/examples/mp3/SoundHelix-Song-2.mp3" type="audio/mpeg">
  </audio>
</div>
', $availaftermod1);

$mod2questions = [
    [
        'name' => 'М2 — Вопрос 1',
        'text' => '<p>Какой размер учебного блока рекомендуется?</p>',
        'answers' => [
            ['text' => '15–20 минут', 'fraction' => 1],
            ['text' => '2–3 часа без перерыва', 'fraction' => 0],
            ['text' => 'Только 5 минут', 'fraction' => 0],
            ['text' => 'Без ограничений', 'fraction' => 0],
        ],
    ],
    [
        'name' => 'М2 — Вопрос 2',
        'text' => '<p>Зачем нужна обратная связь?</p>',
        'answers' => [
            ['text' => 'Поддерживать мотивацию и исправлять ошибки', 'fraction' => 1],
            ['text' => 'Увеличить нагрузку без цели', 'fraction' => 0],
            ['text' => 'Скрыть оценки', 'fraction' => 0],
            ['text' => 'Заменить все занятия', 'fraction' => 0],
        ],
    ],
];
$mod2 = local_kaznu_cli_add_quiz(
    $course,
    3,
    '✅ Тест модуля 2',
    '<p>Тест по модулю 2. Порог прохождения — <strong>60%</strong>.</p>',
    $mod2questions,
    $cat,
    $availaftermod1
);
$availaftermod2 = local_kaznu_cli_completion_availability($mod2['cmid']);

// --- Section 4: Module 3 ---
local_kaznu_cli_set_section_name($course, 4, 'Модуль 3 — Подготовка к экзамену');
local_kaznu_cli_add_label($course, 4, '<div class="local-kaznu-section-label"><h3 class="local-kaznu-mod-title">📙 Модуль 3</h3><p>Итоговая подготовка перед экзаменом.</p></div>', $availaftermod2);
local_kaznu_cli_add_page($course, 4, 'Тема 3.1 — Чек-лист перед экзаменом', '
<div class="local-kaznu-card local-kaznu-checklist">
  <h3>Чек-лист студента</h3>
  <ul>
    <li>✔ Просмотрены все видеолекции</li>
    <li>✔ Прослушаны аудиоматериалы</li>
    <li>✔ Сданы тесты модулей 1 и 2</li>
    <li>✔ Готовность к итоговому экзамену</li>
  </ul>
</div>
', $availaftermod2);
local_kaznu_cli_add_url($course, 4, '🎬 Видео: Подготовка к итоговой аттестации', 'https://www.youtube.com/watch?v=3QIfkeA6HBY', $availaftermod2);

// --- Section 5: Final exam ---
local_kaznu_cli_set_section_name($course, 5, 'Итоговый экзамен');
local_kaznu_cli_add_label($course, 5, '<div class="local-kaznu-section-label"><h3 class="local-kaznu-mod-title">🎓 Итоговый экзамен</h3><p>5 вопросов, 20 минут, порог 60%.</p></div>', $availaftermod2);

$finalexam = [
    [
        'name' => 'Экзамен — Вопрос 1',
        'text' => '<p>Что такое онлайн-обучение?</p>',
        'answers' => [
            ['text' => 'Обучение через интернет с использованием цифровых платформ', 'fraction' => 1],
            ['text' => 'Только очные лекции', 'fraction' => 0],
            ['text' => 'Обучение без заданий', 'fraction' => 0],
            ['text' => 'Только видео без обратной связи', 'fraction' => 0],
        ],
    ],
    [
        'name' => 'Экзамен — Вопрос 2',
        'text' => '<p>Какой формат подходит для летней онлайн-школы?</p>',
        'answers' => [
            ['text' => 'Сочетание видео, заданий и консультаций', 'fraction' => 1],
            ['text' => 'Только письменные экзамены в классе', 'fraction' => 0],
            ['text' => 'Только чтение без дедлайнов', 'fraction' => 0],
            ['text' => 'Только устные ответы', 'fraction' => 0],
        ],
    ],
    [
        'name' => 'Экзамен — Вопрос 3',
        'text' => '<p>Зачем нужен итоговый экзамен?</p>',
        'answers' => [
            ['text' => 'Проверить усвоение и зафиксировать результат', 'fraction' => 1],
            ['text' => 'Увеличить нагрузку без цели', 'fraction' => 0],
            ['text' => 'Заменить все занятия', 'fraction' => 0],
            ['text' => 'Скрыть оценки', 'fraction' => 0],
        ],
    ],
    [
        'name' => 'Экзамен — Вопрос 4',
        'text' => '<p>Что помогает успешно пройти курс?</p>',
        'answers' => [
            ['text' => 'Регулярная работа по плану', 'fraction' => 1],
            ['text' => 'Редкие заходы раз в месяц', 'fraction' => 0],
            ['text' => 'Игнорирование обратной связи', 'fraction' => 0],
            ['text' => 'Отсутствие конспекта', 'fraction' => 0],
        ],
    ],
    [
        'name' => 'Экзамен — Вопрос 5',
        'text' => '<p>Минимальный порог для зачёта?</p>',
        'answers' => [
            ['text' => '60%', 'fraction' => 1],
            ['text' => '40%', 'fraction' => 0],
            ['text' => '90%', 'fraction' => 0],
            ['text' => '100% без ошибок', 'fraction' => 0],
        ],
    ],
];
local_kaznu_cli_add_quiz(
    $course,
    5,
    '🎓 Итоговый экзамен',
    '<p>Ответьте на все вопросы. Для зачёта — <strong>60%</strong> и более. Доступно 2 попытки.</p>',
    $finalexam,
    $cat,
    $availaftermod2,
    1200
);

$teacher = local_kaznu_cli_ensure_user(TEACHER_USER, TEACHER_PASS, 'Алия', 'Преподаватель', 'demo.teacher@moodle.local');
local_kaznu_cli_ensure_user(STUDENT_USER, STUDENT_PASS, 'Айдар', 'Студент', 'demo.student@moodle.local');

local_kaznu_enrol_user($course, (int) $teacher->id, 'editingteacher');
// demo_student записывается через страницу оплаты (демо-поток).

rebuild_course_cache($course->id);
purge_all_caches();

$confirmurl = (new moodle_url('/local/kaznu/pay.php', ['action' => 'confirm', 'token' => 'DEMO-KZN-2026']))->out(false);

cli_writeln('');
cli_writeln('=== Demo ready ===');
cli_writeln('Payment page: ' . $CFG->wwwroot . '/local/kaznu/pay.php');
cli_writeln('Confirm link: ' . $confirmurl);
cli_writeln('Course URL: ' . $CFG->wwwroot . '/course/view.php?id=' . $course->id);
cli_writeln('Teacher: ' . TEACHER_USER . ' / ' . TEACHER_PASS);
cli_writeln('Student (pay to enrol): ' . STUDENT_USER . ' / ' . STUDENT_PASS);
