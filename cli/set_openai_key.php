<?php
/**
 * Set / clear OpenAI API key for Farabi student AI chat.
 *
 * Usage (on Moodle server, as www-data):
 *   php local/kaznu/cli/set_openai_key.php --key=sk-...
 *   php local/kaznu/cli/set_openai_key.php --key=sk-... --enable
 *   php local/kaznu/cli/set_openai_key.php --clear
 *   php local/kaznu/cli/set_openai_key.php --status
 *   php local/kaznu/cli/set_openai_key.php --model=gpt-4o-mini
 *
 * Never print the full key. Do not commit keys to git.
 *
 * @package local_kaznu
 */

define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

list($options, $unrecognized) = cli_get_params([
    'key' => '',
    'clear' => false,
    'enable' => false,
    'disable' => false,
    'model' => '',
    'status' => false,
    'help' => false,
], [
    'h' => 'help',
    's' => 'status',
]);

if (!empty($options['help']) || (
    $options['key'] === '' && empty($options['clear']) && empty($options['enable'])
    && empty($options['disable']) && $options['model'] === '' && empty($options['status'])
)) {
    echo "Configure Farabi student AI chat (OpenAI).\n\n";
    echo "  --key=TOKEN       Store OpenAI API key in plugin config\n";
    echo "  --clear           Remove stored key\n";
    echo "  --enable          Enable AI chat page\n";
    echo "  --disable         Disable AI chat page\n";
    echo "  --model=NAME      Set model (default gpt-4o-mini)\n";
    echo "  --status          Show config status (key masked)\n";
    echo "  --help\n";
    exit(0);
}

if (!empty($options['status'])) {
    $key = (string) get_config('local_kaznu', 'openai_api_key');
    $enabled = (int) get_config('local_kaznu', 'ai_chat_enabled');
    $model = (string) (get_config('local_kaznu', 'openai_model') ?: 'gpt-4o-mini');
    $masked = $key === '' ? '(empty)' : (substr($key, 0, 7) . '…' . substr($key, -4) . ' (len=' . strlen($key) . ')');
    echo "ai_chat_enabled: {$enabled}\n";
    echo "openai_model: {$model}\n";
    echo "openai_api_key: {$masked}\n";
    echo "chat URL: {$CFG->wwwroot}/local/kaznu/chat.php\n";
    exit(0);
}

if (!empty($options['clear'])) {
    set_config('openai_api_key', '', 'local_kaznu');
    echo "OpenAI key cleared.\n";
}

if ($options['key'] !== '') {
    $key = trim((string) $options['key']);
    if (strlen($key) < 20) {
        cli_error('Key looks too short.');
    }
    set_config('openai_api_key', $key, 'local_kaznu');
    echo "OpenAI key saved (masked): " . substr($key, 0, 7) . "…\n";
}

if ($options['model'] !== '') {
    $model = trim((string) $options['model']);
    set_config('openai_model', $model, 'local_kaznu');
    echo "Model set to: {$model}\n";
}

if (!empty($options['enable'])) {
    set_config('ai_chat_enabled', 1, 'local_kaznu');
    echo "AI chat enabled.\n";
}

if (!empty($options['disable'])) {
    set_config('ai_chat_enabled', 0, 'local_kaznu');
    echo "AI chat disabled.\n";
}

exit(0);
