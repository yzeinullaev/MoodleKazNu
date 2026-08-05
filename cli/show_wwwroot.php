<?php
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
echo 'wwwroot=' . $CFG->wwwroot . PHP_EOL;
echo 'cookiesecure=' . (isset($CFG->cookiesecure) ? (string) $CFG->cookiesecure : 'unset') . PHP_EOL;
echo 'sslproxy=' . (!empty($CFG->sslproxy) ? '1' : '0') . PHP_EOL;
echo 'univer_enabled=' . get_config('local_kaznu', 'univer_enabled') . PHP_EOL;
