<?php
// Install and enable language packs: Kazakh, English, Chinese (+ Russian for site default).
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/admin/tool/langimport/classes/controller.php');

$admin = get_admin();
\core\session\manager::set_user($admin);

$toinstall = ['kk', 'en', 'zh_cn', 'ru'];

cli_heading('Installing language packs');

$controller = new \tool_langimport\controller();
$controller->install_languagepacks($toinstall);

foreach ($controller->info as $msg) {
    cli_writeln('INFO: ' . $msg);
}
foreach ($controller->errors as $msg) {
    cli_writeln('ERROR: ' . $msg);
}

// Allow users to choose these languages in the menu.
$langlist = 'kk,en,zh_cn,ru';
set_config('langlist', $langlist);
set_config('langmenu', 1);
set_config('autolang', 1);

purge_all_caches();

cli_writeln('');
cli_writeln('Enabled languages: ' . $langlist);
cli_writeln('Installed in ' . $CFG->dataroot . '/lang/:');
foreach ($toinstall as $lang) {
    $dir = $CFG->dataroot . '/lang/' . $lang;
    $moodlelang = $CFG->dirroot . '/lang/' . $lang;
    if (is_dir($dir) || is_dir($moodlelang)) {
        cli_writeln("  [ok] {$lang}");
    } else {
        cli_writeln("  [??] {$lang} (check manually)");
    }
}
