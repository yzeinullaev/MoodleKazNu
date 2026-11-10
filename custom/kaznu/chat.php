<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

require_login();

if (!local_kaznu_ai_chat_allowed()) {
    throw new moodle_exception('accessdenied', 'admin');
}

$PAGE->set_url(new moodle_url('/local/kaznu/chat.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('aichat', 'local_kaznu'));
$PAGE->set_heading(get_string('aichat', 'local_kaznu'));
$PAGE->requires->css('/local/kaznu/styles.css');
$PAGE->requires->js('/local/kaznu/chat.js');

$webenabled = (bool)get_config('local_kaznu', 'enable_web_search');

echo $OUTPUT->header();

$config = [
    'ajaxurl' => (new moodle_url('/local/kaznu/ajax.php'))->out(false),
    'sesskey' => sesskey(),
    'strings' => [
        'newchat' => get_string('newchat', 'local_kaznu'),
        'send' => get_string('send', 'local_kaznu'),
        'typemessage' => get_string('typemessage', 'local_kaznu'),
        'thinking' => get_string('thinking', 'local_kaznu'),
        'deletechat' => get_string('deletechat', 'local_kaznu'),
        'websearch' => get_string('websearch', 'local_kaznu'),
        'history' => get_string('history', 'local_kaznu'),
    ],
    'webenabled' => $webenabled,
];

echo '<div id="kaznu-ai-root" class="kaznu-ai-app" data-config="' .
    s(json_encode($config, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP)) .
    '"></div>';

echo $OUTPUT->footer();
