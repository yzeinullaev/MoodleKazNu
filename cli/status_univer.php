<?php
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');
echo 'univer_enabled=' . get_config('local_kaznu', 'univer_enabled') . PHP_EOL;
echo 'univer_rl_ip=' . get_config('local_kaznu', 'univer_rl_ip') . PHP_EOL;
echo 'wwwroot=' . $CFG->wwwroot . PHP_EOL;
echo 'version=' . get_config('local_kaznu', 'version') . PHP_EOL;
