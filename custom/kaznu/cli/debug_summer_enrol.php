<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$rows = $DB->get_records_sql("
    SELECT u.username, ue.status, r.shortname AS roleshort
      FROM {user_enrolments} ue
      JOIN {enrol} e ON e.id = ue.enrolid
      JOIN {course} c ON c.id = e.courseid
      JOIN {user} u ON u.id = ue.userid
 LEFT JOIN {context} ctx ON ctx.instanceid = c.id AND ctx.contextlevel = 50
 LEFT JOIN {role_assignments} ra ON ra.userid = u.id AND ra.contextid = ctx.id
 LEFT JOIN {role} r ON r.id = ra.roleid
     WHERE c.shortname = 'SUMMER2026'
");
foreach ($rows as $r) {
    mtrace("{$r->username} status={$r->status} role={$r->roleshort}");
}
