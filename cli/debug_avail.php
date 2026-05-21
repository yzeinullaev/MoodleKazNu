<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$course = local_kaznu_get_demo_course();
$user = core_user::get_user_by_username('demo_student');
if (!$course || !$user) {
    cli_error('Missing course or demo_student');
}

$modinfo = get_fast_modinfo($course->id, $user->id);
cli_writeln("User: {$user->username} ({$user->id})");

foreach ($modinfo->get_cms() as $cm) {
    if (empty($cm->availability)) {
        continue;
    }
    $visible = $cm->uservisible ? 'YES' : 'NO';
    cli_writeln("cm {$cm->id} {$cm->modname} visible={$visible}");
    if (!$cm->uservisible && !empty($cm->availableinfo)) {
        cli_writeln('  info: ' . strip_tags($cm->availableinfo));
    }
}

$gi = $DB->get_record('grade_items', ['courseid' => $course->id, 'itemmodule' => 'quiz', 'iteminstance' => 13]);
$gg = $DB->get_record('grade_grades', ['itemid' => $gi->id, 'userid' => $user->id]);
cli_writeln("Quiz1 grade item {$gi->id}: finalgrade=" . ($gg->finalgrade ?? 'null'));
