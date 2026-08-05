<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

local_kaznu_retire_summer_course();
$c = local_kaznu_get_demo_course();
mtrace($c ? "SUMMER2026 id={$c->id} visible={$c->visible}" : 'missing');

$active = $DB->count_records_sql(
    "SELECT COUNT(1)
       FROM {user_enrolments} ue
       JOIN {enrol} e ON e.id = ue.enrolid
       JOIN {course} c ON c.id = e.courseid
      WHERE c.shortname = ? AND ue.status = 0",
    [LOCAL_KAZNU_COURSE_SHORTNAME]
);
mtrace("active student enrolments: {$active}");
$cat = local_kaznu_get_catalogue_courses(false);
mtrace('catalogue count: ' . count($cat));
foreach ($cat as $row) {
    mtrace(' - ' . $row->shortname);
}
