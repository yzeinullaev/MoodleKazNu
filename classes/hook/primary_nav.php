<?php
namespace local_kaznu\hook;

use core\hook\navigation\primary_extend;
use local_kaznu\access\portal_policy;
use navigation_node;

defined('MOODLE_INTERNAL') || die();

/**
 * Strip redundant Moodle primary nav for ordinary portal users.
 *
 * Home / Dashboard / My courses only redirect back to the unified cabinet —
 * they create clutter and a false sense of multiple destinations.
 */
final class primary_nav {
    public static function simplify(primary_extend $hook): void {
        if (during_initial_install()) {
            return;
        }
        if (!isloggedin() || isguestuser()) {
            return;
        }
        if (!class_exists(portal_policy::class) || !portal_policy::is_portal_user()) {
            return;
        }

        $view = $hook->get_primaryview();

        // Core nodes (see lib/classes/navigation/views/primary.php).
        foreach (['home', 'myhome', 'mycourses'] as $key) {
            $node = $view->find($key, null);
            if ($node) {
                $node->remove();
            }
        }

        // Destinations: cabinet + AI assistant.
        if (!$view->find('local_kaznu_my_cabinet', null)) {
            $cab = $view->add(
                get_string('nav_my_cabinet', 'local_kaznu'),
                new \moodle_url('/local/kaznu/my.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_kaznu_my_cabinet',
                new \pix_icon('i/home', '')
            );
            $cab->showinflatnavigation = true;
        }
        if (!$view->find('local_kaznu_ai_chat', null)) {
            $chat = $view->add(
                get_string('nav_ai_chat', 'local_kaznu'),
                new \moodle_url('/local/kaznu/chat.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_kaznu_ai_chat',
                new \pix_icon('i/messagecontent', '')
            );
            $chat->showinflatnavigation = true;
        }

        // Active state on cabinet / chat.
        global $PAGE;
        $path = (!empty($PAGE) && $PAGE->url) ? $PAGE->url->get_path(false) : '';
        $oncabinet = str_contains($path, '/local/kaznu/my.php');
        $onchat = str_contains($path, '/local/kaznu/chat.php');
        if ($oncabinet) {
            if ($cab = $view->find('local_kaznu_my_cabinet', null)) {
                $cab->make_active();
            }
        }
        if ($onchat) {
            if ($chat = $view->find('local_kaznu_ai_chat', null)) {
                $chat->make_active();
            }
        }
        if ($oncabinet || $onchat) {
            // Prevent Home winning active match by residual URL heuristics.
            foreach (['home', 'myhome', 'mycourses'] as $key) {
                if ($n = $view->find($key, null)) {
                    $n->make_inactive();
                }
            }
        }
    }
}
