<?php
// This file is part of Moodle - http://moodle.org/
//
// php local/proctopus/cli/reconcile_sessions.php --dry-run
// php local/proctopus/cli/reconcile_sessions.php --apply
// Optional: --userid= --cmid= --older-than= (seconds)

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params(
    [
        'help' => false,
        'dry-run' => false,
        'apply' => false,
        'userid' => null,
        'cmid' => null,
        'older-than' => null,
    ],
    [
        'h' => 'help',
    ]
);

if (!empty($options['help']) || (empty($options['dry-run']) && empty($options['apply']))) {
    $help = "Reconcile local Proctopus sessions vs Moodle attempts (no Proctopus API, no DELETE).\n\n"
        . "Options:\n"
        . "  --dry-run              Show proposed changes only\n"
        . "  --apply                Apply proposed terminal statuses\n"
        . "  --userid=ID            Filter user\n"
        . "  --cmid=ID              Filter course module\n"
        . "  --older-than=SECONDS   Only sessions not modified more recently\n"
        . "  -h, --help\n";
    echo $help;
    exit(0);
}

if (!empty($options['dry-run']) && !empty($options['apply'])) {
    cli_error('Use either --dry-run or --apply, not both.');
}

$apply = !empty($options['apply']);
$userid = $options['userid'] !== null && $options['userid'] !== '' ? (int)$options['userid'] : null;
$cmid = $options['cmid'] !== null && $options['cmid'] !== '' ? (int)$options['cmid'] : null;
$older = $options['older-than'] !== null && $options['older-than'] !== '' ? (int)$options['older-than'] : null;

$plan = \local_proctopus\service\proctoring_session_reconciler::reconcile($userid, $cmid, $older, $apply);

cli_writeln(str_pad('id', 6) . str_pad('userid', 8) . str_pad('cmid', 8) . str_pad('attempt', 10)
    . str_pad('status', 18) . str_pad('proposed', 18) . str_pad('att_state', 12) . 'reason');
cli_writeln(str_repeat('-', 100));

$changed = 0;
foreach ($plan as $row) {
    cli_writeln(
        str_pad((string)$row['id'], 6)
        . str_pad((string)$row['userid'], 8)
        . str_pad((string)$row['cmid'], 8)
        . str_pad((string)($row['attemptid'] ?? '-'), 10)
        . str_pad((string)$row['status'], 18)
        . str_pad((string)$row['proposed'], 18)
        . str_pad((string)($row['attempt_state'] ?? '-'), 12)
        . $row['reason']
        . ($row['applied'] ? ' [APPLIED]' : '')
    );
    if (!empty($row['applied'])) {
        $changed++;
    }
}

cli_writeln('');
cli_writeln('Mode: ' . ($apply ? 'APPLY' : 'DRY-RUN') . ' | Reviewed: ' . count($plan) . ' | Applied: ' . $changed);
exit(0);
