<?php
/**
 * Student AI chat page (Farabi School assistant).
 *
 * @package local_kaznu
 */

require_once(__DIR__ . '/../../config.php');

use local_kaznu\service\ai_chat_service;

require_login();
if (isguestuser()) {
    redirect(new moodle_url('/login/index.php'));
}
if (!local_kaznu_student_preview_allowed()) {
    throw new moodle_exception('nopermissions', 'error', '', get_string('nav_ai_chat', 'local_kaznu'));
}

global $USER, $PAGE, $OUTPUT;

$PAGE->set_url(new moodle_url('/local/kaznu/chat.php'));
$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('standard');
$PAGE->set_title(get_string('ai_chat_title', 'local_kaznu'));
$PAGE->set_heading(get_string('ai_chat_title', 'local_kaznu'));
$PAGE->requires->css(new moodle_url('/local/kaznu/styles.css'));

$enabled = (int) get_config('local_kaznu', 'ai_chat_enabled');
$configured = trim((string) get_config('local_kaznu', 'openai_api_key')) !== '';
$firstname = trim((string) $USER->firstname);
if ($firstname === '') {
    $firstname = fullname($USER);
}

$apiurl = (new moodle_url('/local/kaznu/chat_api.php'))->out(false);
$sesskey = sesskey();
$welcome = get_string('ai_chat_welcome', 'local_kaznu', $firstname);

echo $OUTPUT->header();

echo html_writer::start_div('local-kaznu-ai-chat');
echo html_writer::div(
    html_writer::link(new moodle_url('/local/kaznu/my.php'), '← ' . get_string('nav_my_cabinet', 'local_kaznu'), ['class' => 'local-kaznu-ai-back'])
    . html_writer::tag('span', s($firstname), ['class' => 'local-kaznu-ai-userpill']),
    'local-kaznu-ai-toolbar'
);
echo html_writer::tag('p', get_string('ai_chat_lead', 'local_kaznu'), ['class' => 'local-kaznu-ai-lead']);

if (!$enabled || !$configured) {
    echo html_writer::div(get_string('ai_chat_unavailable', 'local_kaznu'), 'alert alert-warning');
    echo html_writer::end_div();
    echo $OUTPUT->footer();
    exit;
}

$suggestions = [
    get_string('ai_chat_suggest_schedule', 'local_kaznu'),
    get_string('ai_chat_suggest_courses', 'local_kaznu'),
    get_string('ai_chat_suggest_grades', 'local_kaznu'),
];

echo html_writer::start_div('local-kaznu-ai-panel');
echo html_writer::start_div('local-kaznu-ai-messages', ['id' => 'kaznu-ai-messages', 'role' => 'log', 'aria-live' => 'polite']);
echo html_writer::div(
    html_writer::tag('div', s($welcome), ['class' => 'local-kaznu-ai-bubble local-kaznu-ai-bubble-bot']),
    'local-kaznu-ai-row local-kaznu-ai-row-bot'
);
echo html_writer::end_div();

echo html_writer::start_div('local-kaznu-ai-suggestions', ['id' => 'kaznu-ai-suggestions']);
foreach ($suggestions as $s) {
    echo html_writer::tag('button', s($s), [
        'type' => 'button',
        'class' => 'local-kaznu-ai-chip',
        'data-prompt' => $s,
    ]);
}
echo html_writer::end_div();

echo html_writer::start_tag('form', [
    'id' => 'kaznu-ai-form',
    'class' => 'local-kaznu-ai-form',
    'autocomplete' => 'off',
]);
echo html_writer::tag('label', get_string('ai_chat_input_label', 'local_kaznu'), [
    'class' => 'sr-only',
    'for' => 'kaznu-ai-input',
]);
echo html_writer::tag('textarea', '', [
    'id' => 'kaznu-ai-input',
    'name' => 'message',
    'rows' => 2,
    'maxlength' => ai_chat_service::MAX_MESSAGE_CHARS,
    'placeholder' => get_string('ai_chat_placeholder', 'local_kaznu'),
    'required' => 'required',
]);
echo html_writer::tag('button', get_string('ai_chat_send', 'local_kaznu'), [
    'type' => 'submit',
    'id' => 'kaznu-ai-send',
    'class' => 'btn btn-primary local-kaznu-ai-send',
]);
echo html_writer::end_tag('form');
echo html_writer::end_div();

echo html_writer::tag('p', get_string('ai_chat_disclaimer', 'local_kaznu'), ['class' => 'local-kaznu-ai-note']);
echo html_writer::end_div();

$apiurljs = json_encode($apiurl, JSON_UNESCAPED_SLASHES);
$sesskeyjs = json_encode($sesskey);
$errgeneric = json_encode(get_string('ai_chat_error_provider', 'local_kaznu'), JSON_UNESCAPED_UNICODE);
$thinking = json_encode(get_string('ai_chat_thinking', 'local_kaznu'), JSON_UNESCAPED_UNICODE);

$js = <<<JS
(function () {
    var form = document.getElementById('kaznu-ai-form');
    var input = document.getElementById('kaznu-ai-input');
    var messages = document.getElementById('kaznu-ai-messages');
    var sendBtn = document.getElementById('kaznu-ai-send');
    if (!form || !input || !messages) { return; }

    var history = [];
    var apiUrl = {$apiurljs};
    var sesskey = {$sesskeyjs};
    var errGeneric = {$errgeneric};
    var thinkingText = {$thinking};
    var busy = false;

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function addBubble(text, who) {
        var row = document.createElement('div');
        row.className = 'local-kaznu-ai-row local-kaznu-ai-row-' + who;
        var bubble = document.createElement('div');
        bubble.className = 'local-kaznu-ai-bubble local-kaznu-ai-bubble-' + who;
        bubble.innerHTML = esc(text).replace(/\\n/g, '<br>');
        row.appendChild(bubble);
        messages.appendChild(row);
        messages.scrollTop = messages.scrollHeight;
        return bubble;
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        if (busy) { return; }
        var text = (input.value || '').trim();
        if (!text) { return; }

        busy = true;
        sendBtn.disabled = true;
        addBubble(text, 'user');
        input.value = '';
        var thinking = addBubble(thinkingText, 'bot');
        thinking.classList.add('local-kaznu-ai-thinking');

        var body = new URLSearchParams();
        body.set('sesskey', sesskey);
        body.set('message', text);
        body.set('history', JSON.stringify(history.slice(-12)));

        fetch(apiUrl, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: body.toString()
        }).then(function (r) { return r.json(); }).then(function (data) {
            if (thinking.parentNode) { thinking.parentNode.remove(); }
            if (data && data.ok && data.reply) {
                addBubble(data.reply, 'bot');
                history.push({role: 'user', content: text});
                history.push({role: 'assistant', content: data.reply});
            } else {
                addBubble((data && data.message) ? data.message : errGeneric, 'bot');
            }
        }).catch(function () {
            if (thinking.parentNode) { thinking.parentNode.remove(); }
            addBubble(errGeneric, 'bot');
        }).finally(function () {
            busy = false;
            sendBtn.disabled = false;
            input.focus();
        });
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            if (form.requestSubmit) { form.requestSubmit(); }
            else { form.dispatchEvent(new Event('submit', {cancelable: true})); }
        }
    });

    var chips = document.querySelectorAll('.local-kaznu-ai-chip');
    chips.forEach(function (chip) {
        chip.addEventListener('click', function () {
            input.value = chip.getAttribute('data-prompt') || chip.textContent || '';
            if (form.requestSubmit) { form.requestSubmit(); }
            else { form.dispatchEvent(new Event('submit', {cancelable: true})); }
        });
    });
})();
JS;

echo html_writer::script($js);
echo $OUTPUT->footer();
