<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/resourcelib.php');

$rows = $DB->get_records_sql("
  SELECT r.id, r.name, r.display, cm.id AS cmid, cm.completion, cm.completionview, c.shortname
    FROM {resource} r
    JOIN {course_modules} cm ON cm.instance = r.id
    JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
    JOIN {course} c ON c.id = r.course
   WHERE c.id > 1
ORDER BY c.shortname, r.id
", null, 0, 50);

foreach ($rows as $r) {
    echo "cm={$r->cmid} display={$r->display} completion={$r->completion} view={$r->completionview}"
        . " | {$r->shortname} | {$r->name}\n";
}
echo "EMBED=" . RESOURCELIB_DISPLAY_EMBED
    . " OPEN=" . RESOURCELIB_DISPLAY_OPEN
    . " DOWNLOAD=" . RESOURCELIB_DISPLAY_DOWNLOAD
    . " POPUP=" . RESOURCELIB_DISPLAY_POPUP
    . " AUTO=" . RESOURCELIB_DISPLAY_AUTO . "\n";
