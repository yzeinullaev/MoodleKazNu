<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$n = local_kaznu_fix_url_video_embed_display();
mtrace("Updated URL display to Embed: {$n}");

$rows = $DB->get_records_sql("
    SELECT u.id, u.name, u.externalurl, u.display, c.shortname
      FROM {url} u
      JOIN {course} c ON c.id = u.course
     WHERE u.externalurl LIKE '%youtube%' OR u.externalurl LIKE '%youtu.be%' OR u.externalurl LIKE '%vimeo%'
  ORDER BY c.shortname, u.id
");
foreach ($rows as $r) {
    mtrace("[{$r->shortname}] display={$r->display} {$r->name}");
}
