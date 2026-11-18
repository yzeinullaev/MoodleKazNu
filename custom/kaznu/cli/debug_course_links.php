<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$courses = $DB->get_records_sql("SELECT id, shortname, fullname FROM {course} WHERE id > 1 ORDER BY id");
foreach ($courses as $c) {
    echo "=== {$c->id} {$c->shortname} ===\n";
    $modinfo = get_fast_modinfo($c);
    foreach ($modinfo->get_cms() as $cm) {
        if (!$cm->url) {
            continue;
        }
        echo "  [{$cm->modname}] {$cm->name} -> " . $cm->url->out(false) . "\n";
    }
    $resume = local_kaznu_resume_url($c, 2);
    echo "  RESUME(user2) -> " . $resume->out(false) . "\n\n";
}
