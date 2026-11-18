<?php
/**
 * Force PDF/file resources to Embed display + completion on view.
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$result = local_kaznu_fix_resource_pdf_embed_display();
mtrace("Updated resource display to Embed: {$result['display']}");
mtrace("Updated resource completion-on-view: {$result['completion']}");

$rows = $DB->get_records_sql("
    SELECT r.id, r.name, r.display, cm.id AS cmid, cm.completion, cm.completionview, c.shortname
      FROM {resource} r
      JOIN {course_modules} cm ON cm.instance = r.id
      JOIN {modules} m ON m.id = cm.module AND m.name = 'resource'
      JOIN {course} c ON c.id = r.course
     WHERE c.id > 1
  ORDER BY c.shortname, r.id
", null, 0, 40);
foreach ($rows as $r) {
    mtrace("[{$r->shortname}] cm={$r->cmid} display={$r->display} completion={$r->completion}/view={$r->completionview} {$r->name}");
}
