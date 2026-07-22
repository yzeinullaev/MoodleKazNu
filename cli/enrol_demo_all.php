<?php
/**
 * Enrol demo_student into all catalogue courses (demo UX).
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$username = 'demo_student';
$user = $DB->get_record('user', ['username' => $username, 'deleted' => 0]);
if (!$user) {
    cli_error("User {$username} not found");
}

local_kaznu_ensure_catalogue_visible();
$count = 0;
foreach (local_kaznu_get_catalogue_courses(true) as $course) {
    if (local_kaznu_user_enrolled_in((int) $user->id, (int) $course->id)) {
        mtrace("already: {$course->shortname}");
        continue;
    }
    // Skip paid summer enrol XP spam — use enrol without awarding if already has XP from summer.
    $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
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
    $plugin->enrol_user($instance, $user->id, $role->id);
    mtrace("enrolled: {$course->shortname}");
    $count++;
}
mtrace("done, new enrolments: {$count}");
