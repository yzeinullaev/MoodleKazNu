<?php
define("CLI_SCRIPT", true);
require(__DIR__ . "/../../../config.php");
require_once($CFG->libdir . "/resourcelib.php");
$cm = get_coursemodule_from_id("resource", 98, 0, false, MUST_EXIST);
$r = $DB->get_record("resource", ["id" => $cm->instance], "*", MUST_EXIST);
echo "cmid={$cm->id} instance={$cm->instance} name={$r->name} display={$r->display}\n";
echo "options={$r->displayoptions}\n";
$ctx = context_module::instance($cm->id);
$files = get_file_storage()->get_area_files($ctx->id, "mod_resource", "content", 0, "sortorder DESC, id ASC", false);
foreach ($files as $f) {
  echo "file={$f->get_filename()} mime={$f->get_mimetype()}\n";
}
