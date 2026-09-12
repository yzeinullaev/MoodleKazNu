<?php
/**
 * End-to-end AI chat smoke as a real Moodle user (no browser).
 *
 * Usage:
 *   sudo -u www-data php local/kaznu/cli/ai_chat_e2e.php --userid=2
 *   sudo -u www-data php local/kaznu/cli/ai_chat_e2e.php --username=student
 *
 * @package local_kaznu
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/filelib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_kaznu\service\ai_chat_service;

list($options, $unrecognized) = cli_get_params([
    'userid' => 0,
    'username' => '',
    'message' => 'Какое у меня расписание?',
    'help' => false,
], ['h' => 'help']);

if (!empty($options['help'])) {
    echo "AI chat e2e smoke\n  --userid=N\n  --username=...\n  --message=...\n";
    exit(0);
}

$user = null;
if (!empty($options['userid'])) {
    $user = $DB->get_record('user', ['id' => (int) $options['userid'], 'deleted' => 0], '*', MUST_EXIST);
} else if ($options['username'] !== '') {
    $user = $DB->get_record('user', ['username' => $options['username'], 'deleted' => 0], '*', MUST_EXIST);
} else {
    // Prefer a linked Univer student if any.
    $sql = "SELECT u.* FROM {user} u
              JOIN {local_kaznu_univer_user} uu ON uu.userid = u.id
             WHERE u.deleted = 0 AND u.suspended = 0
          ORDER BY u.id ASC";
    $user = $DB->get_record_sql($sql, [], IGNORE_MULTIPLE);
    if (!$user) {
        $user = $DB->get_record_sql(
            "SELECT * FROM {user} WHERE deleted=0 AND suspended=0 AND id>1 ORDER BY id ASC",
            [],
            IGNORE_MULTIPLE
        );
    }
}

if (!$user) {
    cli_error('No user found');
}

echo "user={$user->id} {$user->username} " . fullname($user) . "\n";
$svc = new ai_chat_service();
$res = $svc->chat($user, (string) $options['message'], []);
echo "ok=" . (!empty($res['ok']) ? '1' : '0') . "\n";
if (!empty($res['ok'])) {
    echo "reply=" . $res['reply'] . "\n";
    echo "E2E_OK\n";
    exit(0);
}
echo "error=" . ($res['error'] ?? '?') . "\n";
exit(2);
