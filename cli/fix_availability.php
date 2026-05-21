<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/completionlib.php');
require_once(__DIR__ . '/../locallib.php');

$admin = get_admin();
\core\session\manager::set_user($admin);

$course = local_kaznu_get_demo_course();
if (!$course) {
    cli_error('Course SUMMER2026 not found');
}

$quiz1 = $DB->get_record('quiz', ['course' => $course->id, 'name' => '✅ Тест модуля 1'], 'id', MUST_EXIST);
$quiz2 = $DB->get_record('quiz', ['course' => $course->id, 'name' => '✅ Тест модуля 2'], 'id', MUST_EXIST);
$cm1 = get_coursemodule_from_instance('quiz', $quiz1->id, $course->id, MUST_EXIST);
$cm2 = get_coursemodule_from_instance('quiz', $quiz2->id, $course->id, MUST_EXIST);

foreach ([$cm1, $cm2] as $cm) {
    $DB->update_record('course_modules', (object) [
        'id' => $cm->id,
        'completion' => COMPLETION_TRACKING_AUTOMATIC,
        'completiongradeitemnumber' => 0,
        'completionpassgrade' => 1,
        'completionview' => 0,
    ]);
    cli_writeln("Completion enabled on cm {$cm->id}");
}

$avail1 = json_encode([
    'op' => '&',
    'c' => [['type' => 'completion', 'cm' => (int) $cm1->id, 'e' => 2]],
    'showc' => [true],
]);
$avail2 = json_encode([
    'op' => '&',
    'c' => [['type' => 'completion', 'cm' => (int) $cm2->id, 'e' => 2]],
    'showc' => [true],
]);

$sec3 = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 3], MUST_EXIST);
$sec4 = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 4], MUST_EXIST);
$sec5 = $DB->get_field('course_sections', 'id', ['course' => $course->id, 'section' => 5], MUST_EXIST);

$updated = 0;
foreach ($DB->get_records('course_modules', ['course' => $course->id]) as $cm) {
    if (empty($cm->availability)) {
        continue;
    }
    if ((int) $cm->section === (int) $sec3) {
        $av = $avail1;
    } else if (in_array((int) $cm->section, [(int) $sec4, (int) $sec5], true)) {
        $av = $avail2;
    } else {
        continue;
    }
    $DB->set_field('course_modules', 'availability', $av, ['id' => $cm->id]);
    $updated++;
}

$completion = new completion_info($course);
$gi = $DB->get_record('grade_items', [
    'courseid' => $course->id,
    'itemmodule' => 'quiz',
    'iteminstance' => $quiz1->id,
], '*', MUST_EXIST);

foreach ($DB->get_records('grade_grades', ['itemid' => $gi->id]) as $gg) {
    if ($gg->finalgrade !== null && $gg->finalgrade >= 60) {
        $completion->update_state($cm1, COMPLETION_COMPLETE_PASS, (int) $gg->userid);
        cli_writeln("Quiz1 pass completion for user {$gg->userid}");
    }
}

rebuild_course_cache($course->id);
purge_all_caches();
cli_writeln("Updated {$updated} module restrictions.");
