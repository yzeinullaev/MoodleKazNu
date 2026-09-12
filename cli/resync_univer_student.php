<?php
/**
 * Live resync of all Univer student endpoints into local cache.
 *
 * Usage:
 *   sudo -u www-data php local/kaznu/cli/resync_univer_student.php --login=USER --password=PASS
 *   sudo -u www-data php local/kaznu/cli/resync_univer_student.php --login=USER --password=PASS --dump-ai
 *
 * Password is not stored. Cookies cleared after sync.
 *
 * @package local_kaznu
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_kaznu\api\univer_client;
use local_kaznu\service\ai_chat_service;
use local_kaznu\service\full_student_sync_service;
use local_kaznu\service\login_service;

list($options, $unrecognized) = cli_get_params([
    'login' => '',
    'password' => '',
    'dump-ai' => false,
    'help' => false,
], [
    'h' => 'help',
    'l' => 'login',
    'p' => 'password',
]);

if (!empty($options['help']) || $options['login'] === '' || $options['password'] === '') {
    echo "Resync all Univer endpoints into Moodle cache.\n";
    echo "  --login=UNIVER_LOGIN\n";
    echo "  --password=UNIVER_PASSWORD\n";
    echo "  --dump-ai   Print AI context after sync\n";
    exit(empty($options['help']) ? 1 : 0);
}

putenv('LOCAL_KAZNU_ALLOW_HTTP_LOGIN=1');

$login = trim((string) $options['login']);
$password = (string) $options['password'];

$svc = new login_service();
$result = $svc->authenticate($login, $password, '127.0.0.1');
if (!$result->is_success() || empty($result->user)) {
    fwrite(STDERR, "AUTH_FAILED status={$result->status} err={$result->errorcode} outcome={$result->outcome}\n");
    exit(2);
}

$userid = (int) $result->user->id;
echo "AUTH_OK userid={$userid} username={$result->user->username}\n";

// authenticate() already ran full_student_sync_service; dump DB counts.
global $DB;
$uu = $DB->get_record('local_kaznu_univer_user', ['userid' => $userid], '*', MUST_EXIST);
$y = (int) $uu->current_year;
$s = (int) $uu->current_semester;
echo "PROFILE year={$y} sem={$s} faculty={$uu->faculty} speciality={$uu->speciality}\n";

foreach ([[$y, $s], [$y - ($s == 1 ? 1 : 0), $s == 1 ? 2 : $s - 1]] as [$yy, $ss]) {
    if ($yy <= 0 || $ss <= 0) {
        continue;
    }
    $subj = $DB->count_records('local_kaznu_univer_subject', ['userid' => $userid, 'academyear' => $yy, 'academsemester' => $ss]);
    $les = $DB->count_records('local_kaznu_schedule', ['userid' => $userid, 'itemtype' => 'lesson', 'academyear' => $yy, 'academsemester' => $ss]);
    $ex = $DB->count_records('local_kaznu_schedule', ['userid' => $userid, 'itemtype' => 'exam', 'academyear' => $yy, 'academsemester' => $ss]);
    $mid = $DB->count_records('local_kaznu_schedule', ['userid' => $userid, 'itemtype' => 'midterm', 'academyear' => $yy, 'academsemester' => $ss]);
    $as = $DB->count_records('local_kaznu_assessment', ['userid' => $userid, 'academyear' => $yy, 'academsemester' => $ss]);
    $att = $DB->count_records('local_kaznu_attendance', ['userid' => $userid, 'academyear' => $yy, 'academsemester' => $ss]);
    echo "PERIOD {$yy}/{$ss}: subjects={$subj} lessons={$les} exams={$ex} midterms={$mid} assessments={$as} attendance={$att}\n";
}

if (!empty($options['dump-ai'])) {
    $ai = new ai_chat_service();
    $ctx = $ai->build_student_context($result->user);
    echo "--- AI CONTEXT ---\n";
    echo $ctx['text'] . "\n";
    echo "--- END ---\n";
}

echo "RESYNC_OK\n";
exit(0);
