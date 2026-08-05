<?php
/**
 * Seed demo leaderboard users with XP (idempotent).
 */
define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->dirroot . '/user/lib.php');
require_once(__DIR__ . '/../locallib.php');

[$options, $unrecognized] = cli_get_params(['help' => false], ['h' => 'help']);
if ($unrecognized) {
    $unrecognized = implode("\n  ", $unrecognized);
    cli_error(get_string('cliunknowoption', 'admin', $unrecognized));
}
if (!empty($options['help'])) {
    echo "Seed mock Farabi IBS leaderboard users.\n\n";
    echo "  php local/kaznu/cli/seed_leaderboard.php\n";
    exit(0);
}

$seeds = local_kaznu_mock_leaderboard_seed();
$created = 0;
$updated = 0;

foreach ($seeds as $i => $seed) {
    $n = $i + 1;
    $username = sprintf('demoboard_%02d', $n);
    $user = $DB->get_record('user', ['username' => $username, 'mnethostid' => $CFG->mnet_localhost_id]);
    if (!$user) {
        $user = create_user_record($username, generate_password(12));
        $user->firstname = $seed['firstname'];
        $user->lastname = $seed['lastname'];
        $user->email = $username . '@example.invalid';
        $user->confirmed = 1;
        $user->policyagreed = 1;
        $user->maildisplay = 0;
        $user->lang = 'ru';
        $user->city = 'Almaty';
        $user->country = 'KZ';
        user_update_user($user, false, false);
        $created++;
        echo "created {$username}\n";
    } else {
        $user->firstname = $seed['firstname'];
        $user->lastname = $seed['lastname'];
        $user->deleted = 0;
        $user->suspended = 0;
        user_update_user($user, false, false);
    }

    $rec = local_kaznu_get_xp((int) $user->id);
    $rec->xp = (int) $seed['xp'];
    $rec->level = max(1, (int) floor($rec->xp / LOCAL_KAZNU_XP_PER_LEVEL) + 1);
    $rec->timemodified = time();
    $DB->update_record('local_kaznu_xp', $rec);
    $updated++;
    echo "xp {$username}: {$rec->xp} (Lv {$rec->level})\n";
}

echo "Done. created={$created} updated={$updated}\n";
