<?php
/**
 * GPT-style chat with saved history (test / preview page).
 *
 * @package local_kaznu
 */
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

if (!local_kaznu_ai_chat_allowed()) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('nav_ai_chat', 'local_kaznu'));
}

$title = get_string_manager()->string_exists('aichat', 'local_kaznu')
    ? get_string('aichat', 'local_kaznu')
    : get_string('ai_chat_title', 'local_kaznu');

$PAGE->set_url(new moodle_url('/local/kaznu/chat_gpt.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title($title);
$PAGE->set_heading($title . ' (history)');
$PAGE->requires->css(new moodle_url('/local/kaznu/styles_chatgpt.css'));
$PAGE->requires->js(new moodle_url('/local/kaznu/chat.js'));

$webenabled = (bool)get_config('local_kaznu', 'enable_web_search');
if ($webenabled === false) {
    $webenabled = (bool)get_config('local_kaznu', 'ai_chat_web_search');
}

echo $OUTPUT->header();

$config = [
    'ajaxurl' => (new moodle_url('/local/kaznu/ajax.php'))->out(false),
    'sesskey' => sesskey(),
    'strings' => [
        'newchat' => get_string_manager()->string_exists('newchat', 'local_kaznu')
            ? get_string('newchat', 'local_kaznu') : 'Новый чат',
        'send' => get_string('send', 'local_kaznu'),
        'typemessage' => get_string_manager()->string_exists('typemessage', 'local_kaznu')
            ? get_string('typemessage', 'local_kaznu') : 'Введите сообщение…',
        'thinking' => get_string_manager()->string_exists('thinking', 'local_kaznu')
            ? get_string('thinking', 'local_kaznu') : 'Думаю…',
        'deletechat' => get_string_manager()->string_exists('deletechat', 'local_kaznu')
            ? get_string('deletechat', 'local_kaznu') : 'Удалить чат',
        'websearch' => get_string_manager()->string_exists('websearch', 'local_kaznu')
            ? get_string('websearch', 'local_kaznu') : 'Искать в интернете',
        'history' => get_string_manager()->string_exists('history', 'local_kaznu')
            ? get_string('history', 'local_kaznu') : 'История',
    ],
    'webenabled' => $webenabled,
];

echo '<div id="kaznu-ai-root" class="kaznu-ai-app" data-config="' .
    s(json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) .
    '"></div>';

echo $OUTPUT->footer();
