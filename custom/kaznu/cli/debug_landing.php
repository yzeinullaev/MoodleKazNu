<?php
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../locallib.php');

$user = $DB->get_record('user', ['username' => 'demo_student'], '*', MUST_EXIST);
\core\session\manager::set_user($user);

ob_start();
try {
    include __DIR__ . '/../landing.php';
} catch (Throwable $e) {
    fwrite(STDERR, 'FATAL: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() . "\n");
}
$html = ob_get_clean();
echo 'LEN=' . strlen($html) . "\n";
echo 'courses=' . (strpos($html, 'id="courses"') !== false ? 'yes' : 'no') . "\n";
echo 'facts=' . (strpos($html, 'kzn-facts') !== false ? 'yes' : 'no') . "\n";
echo 'tile=' . (strpos($html, 'kzn-tile') !== false ? 'yes' : 'no') . "\n";
echo 'footer=' . (strpos($html, 'kzn-footer') !== false ? 'yes' : 'no') . "\n";
if (preg_match('/Fatal|Exception|Warning|Notice.{0,120}/', $html, $m)) {
    echo 'ERR=' . $m[0] . "\n";
}
