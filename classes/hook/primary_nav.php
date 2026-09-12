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

        $view = $hook->get_primaryview();
        $isportal = class_exists(portal_policy::class) && portal_policy::is_portal_user();

        if ($isportal) {
            // Core nodes (see lib/classes/navigation/views/primary.php).
            foreach (['home', 'myhome', 'mycourses'] as $key) {
                $node = $view->find($key, null);
                if ($node) {
                    $node->remove();
                }
            }

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
        }

        // AI + thesis for allowlisted students and staff who stay on Moodle /my/.
        if (function_exists('local_kaznu_ai_chat_menu_visible')
                ? local_kaznu_ai_chat_menu_visible()
                : ((int) get_config('local_kaznu', 'ai_chat_enabled') === 1)) {
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
        }

        $thesisvisible = function_exists('local_kaznu_thesis_menu_visible')
            ? local_kaznu_thesis_menu_visible()
            : (class_exists(\local_kaznu\service\thesis_service::class)
                && \local_kaznu\service\thesis_service::is_enabled());
        if ($thesisvisible && !$view->find('local_kaznu_thesis', null)) {
            $thesis = $view->add(
                get_string('nav_thesis', 'local_kaznu'),
                new \moodle_url('/local/kaznu/thesis/index.php'),
                navigation_node::TYPE_CUSTOM,
                null,
                'local_kaznu_thesis',
                new \pix_icon('i/report', '')
            );
            $thesis->showinflatnavigation = true;
        }

        if (!$isportal) {
            return;
        }

        // Active state on cabinet / chat / thesis.
        global $PAGE;
        $path = (!empty($PAGE) && $PAGE->url) ? $PAGE->url->get_path(false) : '';
        $oncabinet = str_contains($path, '/local/kaznu/my.php');
        $onchat = str_contains($path, '/local/kaznu/chat.php');
        $onthesis = str_contains($path, '/local/kaznu/thesis/');
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
        if ($onthesis) {
            if ($th = $view->find('local_kaznu_thesis', null)) {
                $th->make_active();
            }
        }
        if ($oncabinet || $onchat || $onthesis) {
            // Prevent Home winning active match by residual URL heuristics.
            foreach (['home', 'myhome', 'mycourses'] as $key) {
                if ($n = $view->find($key, null)) {
                    $n->make_inactive();
                }
            }
        }
    }
}
