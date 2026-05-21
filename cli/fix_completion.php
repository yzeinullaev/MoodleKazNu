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

foreach ($DB->get_records_sql(
    'SELECT cm.id FROM {course_modules} cm
      JOIN {modules} m ON m.id = cm.module AND m.name = ?
      JOIN {quiz} q ON q.id = cm.instance
     WHERE cm.course = ? AND q.name LIKE ?',
    ['quiz', $course->id, '%Тест модуля%']
) as $row) {
    $DB->set_field('course_modules', 'completion', COMPLETION_TRACKING_AUTOMATIC, ['id' => $row->id]);
    $DB->set_field('course_modules', 'completionpassgrade', 1, ['id' => $row->id]);
    $DB->set_field('course_modules', 'completiongradeitemnumber', null, ['id' => $row->id]);
}

local_kaznu_sync_quiz_pass_completions($course);
rebuild_course_cache($course->id);
purge_all_caches();
cli_writeln('Completion records synced.');
