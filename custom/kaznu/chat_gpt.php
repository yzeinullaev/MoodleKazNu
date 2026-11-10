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
$PAGE->set_heading('');
$PAGE->add_body_class('kaznu-ai-chat-page');
$PAGE->requires->css(new moodle_url('/local/kaznu/styles_chatgpt.css'));
$PAGE->requires->js(new moodle_url('/local/kaznu/chat.js'));

$webenabled = (bool)get_config('local_kaznu', 'enable_web_search');
if ($webenabled === false) {
    $webenabled = (bool)get_config('local_kaznu', 'ai_chat_web_search');
}

/**
 * @param string $id
 * @param string $fallback
 */
function local_kaznu_str_or(string $id, string $fallback): string {
    if (get_string_manager()->string_exists($id, 'local_kaznu')) {
        return get_string($id, 'local_kaznu');
    }
    if ($id === 'send' && get_string_manager()->string_exists('sendmessage', 'core')) {
        return get_string('sendmessage', 'core');
    }
    return $fallback;
}

echo $OUTPUT->header();

$config = [
    'ajaxurl' => (new moodle_url('/local/kaznu/ajax.php'))->out(false),
    'sesskey' => sesskey(),
    'strings' => [
        'pagetitle' => $title,
        'subtitle' => local_kaznu_str_or('ai_chat_subtitle', 'Диалоги сохраняются в вашем аккаунте'),
        'assistant' => local_kaznu_str_or('ai_chat_title', 'Умный помощник'),
        'newchat' => local_kaznu_str_or('newchat', 'Новый чат'),
        'send' => local_kaznu_str_or('send', 'Отправить'),
        'typemessage' => local_kaznu_str_or('typemessage', 'Сообщение Farabi AI…'),
        'thinking' => local_kaznu_str_or('thinking', 'Думаю…'),
        'deletechat' => local_kaznu_str_or('deletechat', 'Удалить чат'),
        'deleteconfirm' => local_kaznu_str_or('deletechat_confirm', 'Удалить этот чат?'),
        'websearch' => local_kaznu_str_or('websearch', 'Искать в интернете'),
        'webused' => local_kaznu_str_or('websearch_used', 'Использованы данные из интернета'),
        'history' => local_kaznu_str_or('history', 'История'),
        'welcome_title' => local_kaznu_str_or('ai_welcome_title', 'Чем могу помочь?'),
        'welcome_body' => local_kaznu_str_or('ai_welcome_body', 'Спросите про учёбу, курсы или расписание.'),
        'disclaimer' => local_kaznu_str_or('ai_disclaimer', 'Ответы ИИ носят справочный характер.'),
        'error_generic' => local_kaznu_str_or('error_generic', 'Не удалось получить ответ. Попробуйте позже.'),
        'you' => local_kaznu_str_or('you', 'Вы'),
    ],
    'webenabled' => $webenabled,
];

echo '<div class="kaznu-ai-shell"><div id="kaznu-ai-root" class="kaznu-ai-app" data-config="' .
    s(json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) .
    '"></div></div>';

echo $OUTPUT->footer();
