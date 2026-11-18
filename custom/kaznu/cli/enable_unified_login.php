<?php
/**
 * Explicitly enable unified login (feature flag + auth_univer + optional nologin migration).
 *
 * Usage (as www-data):
 *   php local/kaznu/cli/enable_unified_login.php --enable
 *   php local/kaznu/cli/enable_unified_login.php --disable
 *   php local/kaznu/cli/enable_unified_login.php --status
 *   php local/kaznu/cli/enable_unified_login.php --enable --migrate-nologin
 *
 * Never called automatically by install/upgrade.
 *
 * @package local_kaznu
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
require_once($CFG->libdir . '/adminlib.php');

list($options, $unrecognized) = cli_get_params([
    'enable' => false,
    'disable' => false,
    'status' => false,
    'migrate-nologin' => false,
    'help' => false,
], [
    'e' => 'enable',
    'd' => 'disable',
    's' => 'status',
    'h' => 'help',
]);

if (!empty($options['help']) || (
    empty($options['enable']) && empty($options['disable']) && empty($options['status'])
)) {
    echo "Enable/disable Farabi unified login (auth_univer + feature flag).\n\n";
    echo "  --status              Show current state\n";
    echo "  --enable              Set flag=1 and add auth=univer to enabled plugins\n";
    echo "  --disable             Set flag=0 and remove auth=univer from enabled plugins\n";
    echo "  --migrate-nologin     With --enable: nologin→univer for linked users only\n";
    echo "  --help\n";
    exit(0);
}

$enabledplugins = get_enabled_auth_plugins(true);
$flag = (int) get_config('local_kaznu', 'univer_unified_login_enabled');
$univerapi = (int) get_config('local_kaznu', 'univer_enabled');
$authinstalled = file_exists($CFG->dirroot . '/auth/univer/auth.php');

if (!empty($options['status']) || empty($options['enable']) && empty($options['disable'])) {
    echo "auth_univer files: " . ($authinstalled ? 'present' : 'MISSING') . "\n";
    echo "auth_univer enabled in \$CFG->auth: " . (in_array('univer', $enabledplugins, true) ? 'yes' : 'no') . "\n";
    echo "local_kaznu/univer_unified_login_enabled: {$flag}\n";
    echo "local_kaznu/univer_enabled (API): {$univerapi}\n";
    echo "fallback methods: " . (get_config('local_kaznu', 'univer_fallback_auth_methods') ?: 'manual,email') . "\n";
    if (!empty($options['status'])) {
        exit(0);
    }
}

if (!empty($options['disable'])) {
    set_config('univer_unified_login_enabled', 0, 'local_kaznu');
    $auths = array_values(array_filter(
        array_map('trim', explode(',', $CFG->auth ?? '')),
        static fn($a) => $a !== '' && $a !== 'univer'
    ));
    set_config('auth', implode(',', $auths));
    purge_all_caches();
    echo "DISABLED: flag=0, auth_univer removed from \$CFG->auth\n";
    exit(0);
}

if (!empty($options['enable'])) {
    if (!$authinstalled) {
        cli_error('auth/univer not installed on this wwwroot. Deploy files first.');
    }
    // Ensure plugin is registered in table (if not upgraded yet).
    $pluginman = core_plugin_manager::instance();
    $info = $pluginman->get_plugin_info('auth_univer');
    if (!$info) {
        cli_error('auth_univer not known to plugin manager. Run admin/cli/upgrade.php first.');
    }

    set_config('univer_unified_login_enabled', 1, 'local_kaznu');
    // Keep API switch on when enabling unified login (still administratively controlled).
    if (!(int) get_config('local_kaznu', 'univer_enabled')) {
        echo "NOTE: local_kaznu/univer_enabled is 0 — login service will refuse until you enable API.\n";
    }

    $auths = array_filter(array_map('trim', explode(',', $CFG->auth ?? '')));
    if (!in_array('univer', $auths, true)) {
        array_unshift($auths, 'univer');
    }
    // Always keep manual available for admin recovery.
    if (!in_array('manual', $auths, true)) {
        $auths[] = 'manual';
    }
    set_config('auth', implode(',', array_unique($auths)));

    $migrated = 0;
    if (!empty($options['migrate-nologin'])) {
        global $DB;
        if ($DB->get_manager()->table_exists('local_kaznu_univer_user')) {
            $ids = $DB->get_fieldset_sql(
                "SELECT u.id FROM {user} u
                   JOIN {local_kaznu_univer_user} k ON k.userid = u.id
                  WHERE u.deleted = 0 AND u.auth = 'nologin'"
            );
            foreach ($ids as $id) {
                if (!is_siteadmin((int) $id)) {
                    $DB->set_field('user', 'auth', 'univer', ['id' => (int) $id]);
                    $migrated++;
                }
            }
        }
        echo "Migrated nologin→univer (linked only): {$migrated}\n";
    }

    purge_all_caches();
    echo "ENABLED: flag=1, auth_univer in \$CFG->auth\n";
    echo "Run post-enable smoke and browser matrix before production decision.\n";
    exit(0);
}
