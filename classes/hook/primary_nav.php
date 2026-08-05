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

        // Single destination: unified cabinet.
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

        // Active state on cabinet (any section).
        global $PAGE;
        $oncabinet = false;
        if (!empty($PAGE) && $PAGE->url) {
            $path = $PAGE->url->get_path(false);
            $oncabinet = str_contains($path, '/local/kaznu/my.php');
        }
        if ($oncabinet) {
            if ($cab = $view->find('local_kaznu_my_cabinet', null)) {
                $cab->make_active();
            }
            // Prevent Home winning active match by residual URL heuristics.
            foreach (['home', 'myhome', 'mycourses'] as $key) {
                if ($n = $view->find($key, null)) {
                    $n->make_inactive();
                }
            }
        }
    }
}
