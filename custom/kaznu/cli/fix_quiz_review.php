<?php
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once(__DIR__ . '/../locallib.php');

$admin = get_admin();
\core\session\manager::set_user($admin);

$course = $DB->get_record('course', ['shortname' => LOCAL_KAZNU_COURSE_SHORTNAME]);
if (!$course) {
    cli_error('Course SUMMER2026 not found');
}

foreach ($DB->get_records('quiz', ['course' => $course->id]) as $quiz) {
    local_kaznu_apply_quiz_review_settings((int) $quiz->id);
    cli_writeln("Fixed: {$quiz->name}");
}

rebuild_course_cache($course->id);
purge_all_caches();
cli_writeln('Done.');
