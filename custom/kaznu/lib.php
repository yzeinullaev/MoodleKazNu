<?php
defined('MOODLE_INTERNAL') || die();

/**
 * Is the current user allowed to use AI chat?
 */
function local_kaznu_ai_chat_allowed(): bool {
    global $USER;
    if (isguestuser() || !isloggedin()) {
        return false;
    }
    return has_capability('local/kaznu:useaichat', context_system::instance());
}

/**
 * Add AI chat to navigation.
 */
function local_kaznu_extend_navigation(global_navigation $navigation): void {
    if (!local_kaznu_ai_chat_allowed()) {
        return;
    }
    $node = navigation_node::create(
        get_string('aichat', 'local_kaznu'),
        new moodle_url('/local/kaznu/chat.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kaznu_aichat'
    );
    $navigation->add_node($node);
}
