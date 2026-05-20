<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
$sys = context_system::instance();
echo "system context: {$sys->id}\n";
$cats = $DB->get_records('question_categories', null, 'id ASC', 'id,contextid,parent,name', 0, 10);
echo "count: " . count($cats) . "\n";
foreach ($cats as $c) {
    echo "{$c->id} ctx={$c->contextid} parent={$c->parent} {$c->name}\n";
}
$course = $DB->get_record('course', ['shortname' => 'SUMMER2026']);
if ($course) {
    $ctx = context_course::instance($course->id);
    echo "course context: {$ctx->id}\n";
    $ccats = $DB->get_records('question_categories', ['contextid' => $ctx->id]);
    echo "course cats: " . count($ccats) . "\n";
}
