<?php
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

set_config('univer_enabled', 0, 'local_kaznu');
echo 'univer_enabled=' . get_config('local_kaznu', 'univer_enabled') . PHP_EOL;
echo 'wwwroot=' . $CFG->wwwroot . PHP_EOL;
echo 'sslproxy=' . (!empty($CFG->sslproxy) ? '1' : '0') . PHP_EOL;
echo 'cookiesecure=' . (isset($CFG->cookiesecure) ? (string) $CFG->cookiesecure : 'unset') . PHP_EOL;
echo 'reverseproxy=' . (!empty($CFG->reverseproxy) ? '1' : '0') . PHP_EOL;
echo 'getremoteaddr_headers=' . json_encode($CFG->getremoteaddr_headers ?? null) . PHP_EOL;
