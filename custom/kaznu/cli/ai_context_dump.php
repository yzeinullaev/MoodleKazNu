<?php
/**
 * Dump AI context for a Moodle user (no OpenAI call).
 *
 *   sudo -u www-data php local/kaznu/cli/ai_context_dump.php --userid=31
 *   sudo -u www-data php local/kaznu/cli/ai_context_dump.php --username=demo_student
 *
 * @package local_kaznu
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/enrollib.php');

use local_kaznu\service\ai_chat_service;
use local_kaznu\service\academic_period_resolver;
use local_kaznu\service\student_dashboard_service;

list($options, $unrecognized) = cli_get_params([
    'userid' => 0,
    'username' => '',
    'help' => false,
], ['h' => 'help']);

if (!empty($options['help'])) {
    echo "Dump AI student context\n  --userid=N\n  --username=...\n";
    exit(0);
}

global $DB;
if (!empty($options['userid'])) {
    $user = $DB->get_record('user', ['id' => (int) $options['userid'], 'deleted' => 0], '*', MUST_EXIST);
} else if ($options['username'] !== '') {
    $user = $DB->get_record('user', ['username' => $options['username'], 'deleted' => 0], '*', MUST_EXIST);
} else {
    cli_error('Need --userid or --username');
}

$uu = $DB->get_record('local_kaznu_univer_user', ['userid' => $user->id]);
echo "user={$user->id} {$user->username} " . fullname($user) . "\n";
if ($uu) {
    echo "linked=YES profile_period={$uu->current_year}/{$uu->current_semester}\n";
    $resolved = academic_period_resolver::resolve_for_user(
        (int) $user->id,
        (int) $uu->current_year,
        (int) $uu->current_semester
    );
    echo "resolved_period={$resolved['year']}/{$resolved['semester']} source={$resolved['source']}\n";
    $cab = (new student_dashboard_service())->get_cabinet((int) $user->id, 'grades');
    echo "cabinet_assessments=" . count($cab['assessments'] ?? []) . " year={$cab['year']} sem={$cab['semester']}\n";
    $cab2 = (new student_dashboard_service())->get_cabinet((int) $user->id, 'schedule');
    echo "cabinet_lessons=" . count($cab2['lessons'] ?? []) . " exams=" . count($cab2['exams'] ?? []) . "\n";
} else {
    echo "linked=NO\n";
}

$ctx = (new ai_chat_service())->build_student_context($user);
echo "--- AI CONTEXT ---\n";
echo $ctx['text'] . "\n";
echo "--- END ---\n";
echo "CONTEXT_OK\n";
