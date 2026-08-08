<?php
/**
 * Dry-run / optional apply audit for linked Univer users vs faculty filter.
 *
 * Usage (from Moodle root):
 *   php local/kaznu/cli/faculty_access_audit.php --dry-run
 *   php local/kaznu/cli/faculty_access_audit.php --apply
 *
 * Safe columns only: moodle_userid, univer_userid, faculty_id, decision, reason, auth, role.
 * Never prints name, IIN, password, cookies, or full profile.
 *
 * --apply: suspends Moodle accounts that decide "denied" when filter evaluates
 *          against cached faculty_id + allowlist. Does NOT enable the filter.
 *          Requires confirmation flag LOCAL_KAZNU_AUDIT_APPLY=1 for safety.
 *          Not for production use without separate approval.
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

use local_kaznu\local\faculty_access_policy;

list($options, $unrecognized) = cli_get_params([
    'dry-run' => false,
    'apply' => false,
    'help' => false,
], [
    'h' => 'help',
]);

if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}

if (!empty($options['help']) || (empty($options['dry-run']) && empty($options['apply']))) {
    echo "Faculty access audit for local_kaznu_univer_user links.

Options:
  --dry-run   Report only (default safe mode)
  --apply     Suspend denied accounts (requires env LOCAL_KAZNU_AUDIT_APPLY=1)
  --help

Columns: moodle_userid,univer_userid,faculty_id,decision,reason,auth,role
";
    exit(0);
}

$apply = !empty($options['apply']);
if ($apply) {
    if (getenv('LOCAL_KAZNU_AUDIT_APPLY') !== '1') {
        cli_error('Refusing --apply without LOCAL_KAZNU_AUDIT_APPLY=1 (production safety).');
    }
    cli_writeln('APPLY MODE — will suspend denied non-allowlisted linked users.');
} else {
    cli_writeln('DRY-RUN mode');
}

global $DB;

$hasfacultyid = $DB->get_manager()->field_exists('local_kaznu_univer_user', 'faculty_id');
$sel = 'l.userid AS moodle_userid, l.univer_userid, l.univer_login, u.auth, u.suspended';
if ($hasfacultyid) {
    $sel .= ', l.faculty_id';
}

$sql = "SELECT $sel
          FROM {local_kaznu_univer_user} l
          JOIN {user} u ON u.id = l.userid AND u.deleted = 0
      ORDER BY l.userid ASC";

$rows = $DB->get_records_sql($sql);
$allowedids = faculty_access_policy::parse_allowed_faculty_ids();

cli_writeln('moodle_userid,univer_userid,faculty_id,decision,reason,auth,role');

$toapply = [];
foreach ($rows as $r) {
    $fid = $hasfacultyid && isset($r->faculty_id) && $r->faculty_id !== null && $r->faculty_id !== ''
        ? (int) $r->faculty_id
        : null;
    if ($fid !== null && $fid <= 0) {
        $fid = null;
    }

    $login = (string) ($r->univer_login ?? '');
    $roles = local_kaznu_cli_portal_roles((int) $r->moodle_userid);

    if (faculty_access_policy::is_login_allowlisted($login)
            || faculty_access_policy::is_login_allowlisted((string) $DB->get_field('user', 'username', ['id' => $r->moodle_userid]))) {
        $decision = 'allowed';
        $reason = 'allowlist';
    } else if ($fid === null) {
        $decision = 'unknown';
        $reason = 'faculty_id_missing';
    } else if (in_array($fid, $allowedids, true)) {
        $decision = 'allowed';
        $reason = 'faculty_ok';
    } else {
        $decision = 'denied';
        $reason = 'faculty_denied';
        $toapply[] = (int) $r->moodle_userid;
    }

    $fidout = $fid === null ? '' : (string) $fid;
    cli_writeln(implode(',', [
        (int) $r->moodle_userid,
        (int) $r->univer_userid,
        $fidout,
        $decision,
        $reason,
        (string) $r->auth,
        $roles,
    ]));
}

if ($apply && $toapply) {
    $n = 0;
    foreach (array_unique($toapply) as $userid) {
        if (is_siteadmin($userid)) {
            cli_writeln("skip siteadmin userid=$userid");
            continue;
        }
        $DB->set_field('user', 'suspended', 1, ['id' => $userid]);
        $n++;
        cli_writeln("suspended userid=$userid");
    }
    cli_writeln("apply complete, suspended=$n");
} else if ($apply) {
    cli_writeln('apply complete, nothing to suspend');
}

/**
 * Safe role shortnames only.
 */
function local_kaznu_cli_portal_roles(int $userid): string {
    global $DB;
    $sys = \context_system::instance();
    $roles = get_user_roles($sys, $userid, false);
    $shorts = [];
    foreach ($roles as $ra) {
        $shorts[] = $ra->shortname;
    }
    // Also common named roles if assigned elsewhere (keep compact).
    sort($shorts);
    return $shorts ? implode('|', array_unique($shorts)) : '';
}
