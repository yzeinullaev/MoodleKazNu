<?php
/**
 * One-shot OpenAI smoke test for Farabi AI chat.
 * @package local_kaznu
 */
define('CLI_SCRIPT', true);
require(__DIR__ . '/../../../config.php');

$key = trim((string) get_config('local_kaznu', 'openai_api_key'));
$enabled = (int) get_config('local_kaznu', 'ai_chat_enabled');
$model = (string) (get_config('local_kaznu', 'openai_model') ?: 'gpt-4o-mini');

echo "enabled={$enabled}\n";
echo "model={$model}\n";
echo "key_len=" . strlen($key) . "\n";

if ($key === '' || !$enabled) {
    fwrite(STDERR, "NOT_READY\n");
    exit(1);
}

$curl = new curl();
$curl->setHeader([
    'Content-Type: application/json',
    'Authorization: Bearer ' . $key,
]);
$raw = $curl->post('https://api.openai.com/v1/chat/completions', json_encode([
    'model' => $model,
    'messages' => [
        ['role' => 'system', 'content' => 'Ответь очень кратко.'],
        ['role' => 'user', 'content' => 'Скажи: чат работает'],
    ],
    'max_tokens' => 30,
], JSON_UNESCAPED_UNICODE));
$info = $curl->get_info();
$http = (int) ($info['http_code'] ?? 0);
echo "http={$http}\n";
$data = json_decode((string) $raw, true);
$reply = is_array($data) ? (string) ($data['choices'][0]['message']['content'] ?? '') : '';
if ($reply === '') {
    echo "body=" . substr((string) $raw, 0, 300) . "\n";
    exit(2);
}
echo "reply={$reply}\n";
echo "SMOKE_OK\n";
