<?php
/**
 * Switch Moodle wwwroot to HTTPS domain + secure cookie flags.
 * php /tmp/moodle_https_wwwroot.php
 */
define('CLI_SCRIPT', true);
require('/var/www/moodle/config.php');

// config.php hardcodes wwwroot — we patch the file.
$configpath = '/var/www/moodle/config.php';
$raw = file_get_contents($configpath);
if ($raw === false) {
    fwrite(STDERR, "Cannot read config.php\n");
    exit(1);
}

$newroot = 'https://farabischool.kz';
if (!preg_match('/\$CFG->wwwroot\s*=/', $raw)) {
    fwrite(STDERR, "wwwroot assignment not found\n");
    exit(1);
}

$raw2 = preg_replace(
    '/\$CFG->wwwroot\s*=\s*[\'"][^\'"]*[\'"]\s*;/',
    "\$CFG->wwwroot = '{$newroot}';",
    $raw,
    1,
    $count
);
if ($count !== 1) {
    fwrite(STDERR, "Failed to replace wwwroot (count={$count})\n");
    exit(1);
}

// Ensure secure cookies when on HTTPS (config.php may already set cookiesecure).
if (str_contains($raw2, 'cookiesecure')) {
    $raw2 = preg_replace(
        '/\$CFG->cookiesecure\s*=\s*[^;]+;/',
        '$CFG->cookiesecure = 1;',
        $raw2,
        1
    );
} else {
    $raw2 = preg_replace(
        '/(\$CFG->wwwroot\s*=\s*[^;]+;)/',
        "$1\n\$CFG->cookiesecure = 1;",
        $raw2,
        1
    );
}

// Not behind reverse TLS terminator on this host.
if (str_contains($raw2, 'sslproxy')) {
    $raw2 = preg_replace('/\$CFG->sslproxy\s*=\s*[^;]+;/', '$CFG->sslproxy = false;', $raw2, 1);
}

// Backup once.
$bak = $configpath . '.bak-' . date('YmdHis');
if (!copy($configpath, $bak)) {
    fwrite(STDERR, "Backup failed\n");
    exit(1);
}
if (file_put_contents($configpath, $raw2) === false) {
    fwrite(STDERR, "Write failed\n");
    exit(1);
}

// Reset opcache if available.
if (function_exists('opcache_reset')) {
    opcache_reset();
}

// Re-read by including again is messy in same process; print and DB-side configs.
echo "Patched config.php backup={$bak}\n";
echo "wwwroot line now: ";
preg_match('/\$CFG->wwwroot\s*=\s*[^;]+;/', $raw2, $m);
echo ($m[0] ?? '?') . "\n";
