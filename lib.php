<?php
defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/locallib.php');

/**
 * Early redirect: guests on course/view → branded hub (before require_login).
 */
function local_kaznu_after_config() {
    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
        return;
    }
    if (function_exists('during_initial_install') && during_initial_install()) {
        return;
    }

    $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    if ($script === '' || substr($script, -strlen('/course/view.php')) !== '/course/view.php') {
        if ($script !== '/course/view.php' && $script !== 'course/view.php') {
            return;
        }
    }

    $loggedin = false;
    if (!empty($GLOBALS['USER']) && !empty($GLOBALS['USER']->id) && (int) $GLOBALS['USER']->id > 0) {
        $loggedin = empty($GLOBALS['USER']->username) || $GLOBALS['USER']->username !== 'guest';
        if (function_exists('isloggedin') && function_exists('isguestuser')) {
            $loggedin = isloggedin() && !isguestuser();
        }
    }

    if ($loggedin) {
        return;
    }

    $courseid = isset($_GET['id']) ? (int) $_GET['id'] : 0;
    if ($courseid <= 1) {
        return;
    }

    if (class_exists('moodle_url')) {
        redirect(new moodle_url('/local/kaznu/course.php', ['id' => $courseid]));
    }

    $www = !empty($GLOBALS['CFG']->wwwroot) ? $GLOBALS['CFG']->wwwroot : '';
    header('Location: ' . $www . '/local/kaznu/course.php?id=' . $courseid, true, 303);
    exit;
}

/**
 * Redirect frontpage → landing.
 */
function local_kaznu_before_http_headers() {
    if ((defined('CLI_SCRIPT') && CLI_SCRIPT) || (defined('AJAX_SCRIPT') && AJAX_SCRIPT)) {
        return;
    }
    if (function_exists('during_initial_install') && during_initial_install()) {
        return;
    }

    global $SCRIPT, $DB, $USER;

    // Phase 6A/C: portal student URL policy (server-side).
    if (class_exists(\local_kaznu\local\access_guard::class)) {
        \local_kaznu\local\access_guard::maybe_enforce();
    }

    $script = (string) ($SCRIPT ?? '');
    if ($script === '') {
        $script = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
    }

    if ($script === '/index.php' || $script === 'index.php') {
        redirect(new moodle_url('/local/kaznu/landing.php'));
    }

    // Block student access to retired summer school (course + activities).
    $iscourse = (substr($script, -strlen('/course/view.php')) === '/course/view.php'
        || $script === '/course/view.php' || $script === 'course/view.php');
    $ismod = (strpos($script, '/mod/') !== false);
    if ($iscourse || $ismod) {
        $courseid = 0;
        if ($iscourse && isset($_GET['id'])) {
            $courseid = (int) $_GET['id'];
        } else if ($ismod && isset($_GET['id'])) {
            // Activity cmid → course.
            require_once(__DIR__ . '/locallib.php');
            $cm = $DB->get_record('course_modules', ['id' => (int) $_GET['id']], 'course');
            $courseid = $cm ? (int) $cm->course : 0;
        }
        if ($courseid > 1) {
            require_once(__DIR__ . '/locallib.php');
            $course = $DB->get_record('course', ['id' => $courseid], 'id,shortname');
            if ($course && local_kaznu_is_retired_course($course)) {
                $sys = context_system::instance();
                $isadmin = isloggedin() && !isguestuser() && has_capability('moodle/site:config', $sys);
                if (!$isadmin) {
                    redirect(
                        new moodle_url('/local/kaznu/landing.php'),
                        get_string('course_retired', 'local_kaznu'),
                        null,
                        \core\output\notification::NOTIFY_INFO
                    );
                }
            }
        }
    }
}

/**
 * Student-facing Moodle areas that get Farabi IBS skin.
 */
function local_kaznu_is_student_shell_page(): bool {
    global $PAGE, $COURSE;

    if (!empty($COURSE->id) && (int) $COURSE->id > 1) {
        return true;
    }

    $type = (string) ($PAGE->pagetype ?? '');
    $path = $PAGE->url ? (string) $PAGE->url->get_path() : '';

    $prefixes = ['mod-', 'my-', 'user-', 'calendar-', 'grade-', 'message-', 'blog-', 'notes-', 'badges-', 'report-'];
    foreach ($prefixes as $p) {
        if (strpos($type, $p) === 0) {
            return true;
        }
    }

    if (in_array($type, [
        'local-kaznu-pay',
        'local-kaznu-landing',
        'local-kaznu-course',
        'course-view-topics',
        'course-view-tiles',
        'mycourses-index',
        'my-index',
    ], true)) {
        return true;
    }

    foreach (['/local/kaznu/', '/course/', '/mod/', '/my/', '/user/', '/calendar/', '/grade/', '/message/'] as $needle) {
        if (strpos($path, $needle) !== false) {
            return true;
        }
    }

    return false;
}

/**
 * Whether Farabi IBS styles should load.
 */
function local_kaznu_should_load_styles(): bool {
    return local_kaznu_is_student_shell_page();
}

/**
 * Register plugin stylesheet.
 */
function local_kaznu_load_styles(): void {
    global $PAGE;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    $sheet = new moodle_url('/local/kaznu/styles.css', ['rev' => get_config('local_kaznu', 'version') ?: '2026072207']);
    $PAGE->requires->css($sheet);
}

function local_kaznu_before_standard_html_head() {
    // Quiz focus chrome is selected via Moodle page body id/classes
    // (page-mod-quiz-attempt|summary|processattempt). Do not call
    // add_body_class here — some quiz layouts start output early and it fatals.
    local_kaznu_load_styles();
}

/**
 * Fallback: direct link tag + brand fonts.
 */
function local_kaznu_before_standard_head_html() {
    global $CFG;

    if (!local_kaznu_should_load_styles()) {
        return;
    }

    $favicon = $CFG->wwwroot . '/local/kaznu/pix/favicon.svg';
    echo '<link rel="icon" href="' . s($favicon) . '" type="image/svg+xml">' . "\n";

    echo '<link rel="preconnect" href="https://fonts.googleapis.com">' . "\n";
    echo '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>' . "\n";
    // Manrope — readable Cyrillic; Cormorant for display headings.
    echo '<link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600;700&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">' . "\n";

    $rev = get_config('local_kaznu', 'version') ?: '2026072207';
    $href = $CFG->wwwroot . '/local/kaznu/styles.css?rev=' . $rev;
    echo '<link rel="stylesheet" type="text/css" href="' . s($href) . '" />' . "\n";
}

/**
 * Arena / dashboard / XP HUD / celebration.
 */
function local_kaznu_before_footer() {
    global $USER, $COURSE, $SESSION, $PAGE;

    if (!isloggedin() || isguestuser()) {
        return;
    }

    require_once(__DIR__ . '/locallib.php');

    $pagetype = (string) $PAGE->pagetype;
    $ondash = ($pagetype === 'my-index' || $pagetype === 'mycourses-index' || strpos($pagetype, 'my-') === 0);
    $oncoursehome = (strpos($pagetype, 'course-view') === 0);
    $onmod = (strpos($pagetype, 'mod-') === 0);
    $quizfocus = local_kaznu_is_quiz_focus_page();

    $dbman = $GLOBALS['DB']->get_manager();
    $hasxp = $dbman->table_exists('local_kaznu_xp');

    // Celebration after XP award (not during active attempt UI).
    if ($hasxp && !empty($SESSION->local_kaznu_celebrate) && !$quizfocus) {
        $c = $SESSION->local_kaznu_celebrate;
        unset($SESSION->local_kaznu_celebrate);
        $title = !empty($c['levelup'])
            ? get_string('xp_levelup', 'local_kaznu', (object) ['level' => $c['level'], 'title' => $c['title']])
            : get_string('xp_gained_title', 'local_kaznu');
        $detail = get_string('xp_gained_detail', 'local_kaznu', (object) [
            'gained' => (int) $c['gained'],
            'total' => (int) $c['xp'],
            'level' => (int) $c['level'],
        ]);
        echo '<div class="local-kaznu-celebrate" role="status" aria-live="polite">'
            . '<div class="lvl">' . s($title) . '</div>'
            . '<div class="xp">' . s($detail) . '</div>'
            . '<div class="gain">+' . (int) $c['gained'] . ' XP</div>'
            . '</div>';
    } else if ($hasxp && !empty($SESSION->local_kaznu_celebrate) && $quizfocus) {
        // Keep session flag until a non-focus page (e.g. course) so toast is not lost mid-submit.
    }

    if ($hasxp && $ondash) {
        echo local_kaznu_render_dashboard_arena($USER);
        echo '<script>(function(){var d=document.querySelector("[data-kaznu-dash]");var m=document.getElementById("region-main");if(d&&m){m.insertBefore(d,m.firstChild);}var h=document.querySelector("#page-header");if(h){h.classList.add("kzn-hide-default-header");}})();</script>';
    }

    if ($hasxp && $oncoursehome && !empty($COURSE->id) && (int) $COURSE->id > 1) {
        echo local_kaznu_render_course_arena($COURSE, (int) $USER->id);
        echo '<script>(function(){var a=document.querySelector("[data-kaznu-arena]");var m=document.getElementById("region-main");if(a&&m){m.insertBefore(a,m.firstChild);}})();</script>';
    }

    // Sticky achievements HUD: collapsible; never on quiz focus pages (no HTML).
    if ($hasxp && !$quizfocus && local_kaznu_is_student_shell_page()) {
        $xp = local_kaznu_get_xp((int) $USER->id);
        $prog = local_kaznu_xp_progress($xp);
        $catalog = new moodle_url('/local/kaznu/landing.php');
        $hub = (!empty($COURSE->id) && (int) $COURSE->id > 1)
            ? new moodle_url('/local/kaznu/course.php', ['id' => $COURSE->id])
            : new moodle_url('/my/');
        $hublabel = (!empty($COURSE->id) && (int) $COURSE->id > 1)
            ? get_string('hub_syllabus', 'local_kaznu')
            : get_string('dash_title', 'local_kaznu');

        $body = '<p class="kzn-achievement-rank">' . s($prog['title']) . '</p>'
            . '<div class="kzn-achievement-bar" role="progressbar" aria-valuemin="0" aria-valuemax="100"'
            . ' aria-valuenow="' . (int) $prog['pct'] . '"><span style="width:' . (int) $prog['pct'] . '%"></span></div>'
            . '<p class="kzn-achievement-to-next">' . get_string('xp_to_next', 'local_kaznu', (int) ($prog['need'] - $prog['into'])) . '</p>'
            . '<p class="kzn-achievement-links">'
            . '<a href="' . $hub->out(false) . '">' . s($hublabel) . '</a>'
            . ' · <a href="' . $catalog->out(false) . '">' . get_string('landing_nav_courses', 'local_kaznu') . '</a>'
            . '</p>';

        echo local_kaznu_render_achievement_panel([
            'id' => 'kzn-achievements-hud',
            'title' => get_string('xp_quiz_panel_title', 'local_kaznu'),
            'level' => (int) $xp->level,
            'xp' => (int) $xp->xp,
            'bodyhtml' => $body,
            'variant' => 'hud',
            'extra_class' => 'local-kaznu-hud',
        ]);
    }

    // Lesson strip / nav / quiz intro XP — not during quiz attempt, summary, process, review.
    if ($hasxp && !$quizfocus && local_kaznu_is_student_shell_page()) {
        $xp = local_kaznu_get_xp((int) $USER->id);

        if ($onmod && !empty($COURSE->id) && (int) $COURSE->id > 1) {
            echo '<div class="local-kaznu-lesson-strip" data-kaznu-strip="1">'
                . '<a href="' . (new moodle_url('/course/view.php', ['id' => $COURSE->id]))->out(false) . '">'
                . format_string($COURSE->fullname) . '</a>'
                . '<span>Lv ' . (int) $xp->level . ' · ' . (int) $xp->xp . ' XP</span>'
                . '</div>';
            echo '<script>(function(){var s=document.querySelector("[data-kaznu-strip]");var m=document.getElementById("region-main");if(s&&m){m.insertBefore(s,m.firstChild);}})();</script>';

            $cmid = 0;
            if (!empty($PAGE->cm) && !empty($PAGE->cm->id)) {
                $cmid = (int) $PAGE->cm->id;
            } else if ($PAGE->context && $PAGE->context->contextlevel == CONTEXT_MODULE) {
                $cmid = (int) $PAGE->context->instanceid;
            }
            if ($cmid > 0) {
                $afternext = (strpos($pagetype, 'mod-quiz-view') === 0);
                echo local_kaznu_render_lesson_nav($COURSE, $cmid, (int) $USER->id, $afternext);
                echo '<script>(function(){var n=document.querySelector("[data-kaznu-nav]");var m=document.getElementById("region-main");if(n&&m){m.appendChild(n);}})();</script>';

                // XP rules only on quiz intro (view) — not on attempt/summary/process/review.
                if (strpos($pagetype, 'mod-quiz-view') === 0) {
                    $quizid = 0;
                    if (!empty($PAGE->cm) && $PAGE->cm->modname === 'quiz') {
                        $quizid = (int) $PAGE->cm->instance;
                    } else {
                        $cmquiz = get_coursemodule_from_id('quiz', $cmid, 0, false);
                        $quizid = $cmquiz ? (int) $cmquiz->instance : 0;
                    }
                    if ($quizid > 0) {
                        $panel = local_kaznu_render_quiz_xp_panel($COURSE, (int) $USER->id, $quizid);
                        if ($panel !== '') {
                            echo $panel;
                            echo '<script>(function(){var p=document.querySelector("[data-kzn-achievements].kzn-xp-panel");var m=document.getElementById("region-main");if(p&&m){var s=m.querySelector("[data-kaznu-strip]");if(s&&s.nextSibling){m.insertBefore(p,s.nextSibling);}else{m.insertBefore(p,m.firstChild);}}})();</script>';
                        }
                    }
                }

                // Embed YouTube/Vimeo only when Moodle did not already embed them.
                if (strpos($pagetype, 'mod-url-view') === 0) {
                    global $CFG, $DB;
                    require_once($CFG->libdir . '/resourcelib.php');
                    if (!empty($PAGE->cm) && !empty($PAGE->cm->instance)) {
                        $urlrec = $DB->get_record('url', ['id' => (int) $PAGE->cm->instance], 'externalurl,display');
                    } else {
                        $cmrec = get_coursemodule_from_id('url', $cmid, 0, false);
                        $urlrec = $cmrec
                            ? $DB->get_record('url', ['id' => (int) $cmrec->instance], 'externalurl,display')
                            : false;
                    }
                    if ($urlrec && !empty($urlrec->externalurl)) {
                        $alreadyembed = ((int) $urlrec->display === RESOURCELIB_DISPLAY_EMBED);
                        $embed = '';
                        if (!$alreadyembed) {
                            $embed = local_kaznu_youtube_embed_from_url($urlrec->externalurl);
                            if ($embed === '') {
                                $embed = local_kaznu_vimeo_embed_from_url($urlrec->externalurl);
                            }
                        }
                        if ($embed !== '') {
                            echo $embed;
                        }
                        echo '<script>(function(){'
                            . 'var m=document.getElementById("region-main");if(!m)return;'
                            . 'var native=m.querySelector(".mediaplugin iframe,iframe[src*=\\"youtube\\"],iframe[src*=\\"youtu.be\\"],iframe[src*=\\"vimeo\\"]");'
                            . 'var y=document.querySelector("[data-kaznu-yt]:not([data-kaznu-pdf])");'
                            . 'if(native){if(y){y.remove();}native.classList.add("kzn-media-frame");}'
                            . 'else if(y){var box=m.querySelector(".activity-description")||m.querySelector("[role=main]")||m;'
                            . 'var host=box.querySelector(".no-overflow")||box;host.insertBefore(y,host.firstChild);}'
                            . 'document.querySelectorAll(".urlworkaround,.resourceworkaround").forEach(function(el){el.style.display="none";});'
                            . '})();</script>';
                    }
                }

                // PDF: use Moodle embed when already Embed; otherwise inject one frame only.
                if (strpos($pagetype, 'mod-resource-view') === 0) {
                    global $CFG, $DB;
                    require_once($CFG->libdir . '/completionlib.php');
                    require_once($CFG->libdir . '/resourcelib.php');
                    $title = (!empty($PAGE->cm) && !empty($PAGE->cm->name)) ? format_string($PAGE->cm->name) : '';
                    $resinstance = 0;
                    if (!empty($PAGE->cm) && !empty($PAGE->cm->instance)) {
                        $resinstance = (int) $PAGE->cm->instance;
                    } else {
                        $cmres = get_coursemodule_from_id('resource', $cmid, 0, false);
                        $resinstance = $cmres ? (int) $cmres->instance : 0;
                    }
                    $resdisplay = $resinstance
                        ? (int) $DB->get_field('resource', 'display', ['id' => $resinstance])
                        : 0;
                    $alreadyembed = ($resdisplay === RESOURCELIB_DISPLAY_EMBED);
                    if (!$alreadyembed) {
                        $pdfembed = local_kaznu_render_resource_embed($cmid, $title);
                        if ($pdfembed !== '') {
                            echo $pdfembed;
                        }
                    }
                    echo '<script>(function(){'
                        . 'var m=document.getElementById("region-main");if(!m)return;'
                        . 'var box=m.querySelector(".resourcecontent")||m.querySelector(".activity-description")||m.querySelector("[role=main]")||m;'
                        . 'var native=box.querySelector("iframe,object,embed");'
                        . 'var p=document.querySelector("[data-kaznu-pdf]");'
                        . 'if(native){if(p){p.remove();}'
                        . 'if(native.tagName==="IFRAME"){native.classList.add("kzn-pdf-frame");'
                        . 'var wrap=document.createElement("div");wrap.className="local-kaznu-pdf kzn-pdf-native";'
                        . 'native.parentNode.insertBefore(wrap,native);wrap.appendChild(native);}'
                        . '}else if(p){box.insertBefore(p,box.firstChild);}'
                        . 'document.querySelectorAll(".resourceworkaround,.urlworkaround").forEach(function(el){el.style.display="none";});'
                        . '})();</script>';
                    if (isloggedin() && !isguestuser() && !empty($COURSE->id)) {
                        $cmobj = null;
                        if (!empty($PAGE->cm)) {
                            $cmobj = $PAGE->cm;
                        } else {
                            $modinfo = get_fast_modinfo($COURSE);
                            $cmobj = $modinfo->cms[$cmid] ?? null;
                        }
                        if ($cmobj) {
                            $completion = new completion_info($COURSE);
                            if ($completion->is_enabled($cmobj)) {
                                $completion->set_module_viewed($cmobj);
                            }
                        }
                    }
                }
            }
        }
    }

    if (local_kaznu_should_debrand()) {
        $brand = get_string('landing_brand', 'local_kaznu');
        $langdd = local_kaznu_render_lang_dropdown();
        echo $langdd;
        echo '<script>(function(){'
            . 'document.body.classList.add("local-kaznu-debrand","local-kaznu-hide-temp");'
            . 'var b=document.querySelector(".navbar-brand");'
            // Single-line brand — two-line + slogan breaks Boost navbar height/flex.
            . 'if(b){b.innerHTML=' . json_encode('<span class="kzn-nav-brand"><strong>' . $brand . '</strong></span>') . ';'
            . 'b.href=' . json_encode((new moodle_url('/local/kaznu/landing.php'))->out(false)) . ';}'
            . 'document.title=document.title.replace(/Moodle LMS?/gi,' . json_encode($brand) . ');'
            // Lang dropdown next to usermenu only — never inside primary-navigation.
            . 'var dd=document.querySelector("[data-kaznu-lang]");'
            . 'var um=document.querySelector(".navbar .usermenu");'
            . 'var host=um?(um.closest(".navbar-nav")||um.parentNode):document.querySelector(".navbar .navbar-nav:last-of-type");'
            . 'if(dd&&host&&host.parentNode){host.parentNode.insertBefore(dd,host);}'
            // Hide temporary Moodle clutter on student dashboard.
            . 'document.querySelectorAll(".block_timeline,.block_calendar_month,.block_calendar_upcoming,'
            . '.block_recentlyaccessedcourses,.block_recentlyaccesseditems,.block_myoverview,'
            . '.activity-navigation").forEach(function(el){el.classList.add("kzn-temp-hidden");});'
            . '})();</script>';
        echo '<script>(function(){'
            . 'document.querySelectorAll("[data-kaznu-lang]").forEach(function(root){'
            . 'var btn=root.querySelector(".kzn-lang-btn");var menu=root.querySelector(".kzn-lang-menu");'
            . 'if(!btn||!menu)return;'
            . 'btn.addEventListener("click",function(e){e.stopPropagation();'
            . 'var open=btn.getAttribute("aria-expanded")==="true";'
            . 'btn.setAttribute("aria-expanded",open?"false":"true");menu.hidden=open;});'
            . 'document.addEventListener("click",function(){btn.setAttribute("aria-expanded","false");menu.hidden=true;});'
            . '});})();</script>';
    }
}

/**
 * Landing + cabinet links in primary navigation.
 */
function local_kaznu_extend_navigation(global_navigation $nav) {
    global $PAGE;

    $isportal = class_exists(\local_kaznu\access\portal_policy::class)
        && \local_kaznu\access\portal_policy::is_portal_user();

    // Portal primary bar is handled by primary_nav hook (home/my/courses hidden).
    // Still add cabinet to global nav for legacy flat nav paths.
    $oncabinet = false;
    if (!empty($PAGE) && $PAGE->url) {
        $path = $PAGE->url->get_path(false);
        $oncabinet = str_contains($path, '/local/kaznu/my.php');
    }

    if ($isportal) {
        $cab = $nav->add(
            get_string('nav_my_cabinet', 'local_kaznu'),
            new moodle_url('/local/kaznu/my.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_kaznu_my_cabinet',
            new pix_icon('i/home', '')
        );
        $cab->showinflatnavigation = false; // Primary hook owns the bar.
        if ($oncabinet) {
            $cab->make_active();
        }
        $chat = $nav->add(
            get_string('nav_ai_chat', 'local_kaznu'),
            new moodle_url('/local/kaznu/chat.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_kaznu_ai_chat',
            new pix_icon('i/messagecontent', '')
        );
        $chat->showinflatnavigation = false;
        if (!empty($PAGE) && $PAGE->url && str_contains($PAGE->url->get_path(false), '/local/kaznu/chat.php')) {
            $chat->make_active();
        }
        return;
    }

    $home = $nav->add(
        get_string('landing_title', 'local_kaznu'),
        new moodle_url('/local/kaznu/landing.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kaznu_landing',
        new pix_icon('i/home', '')
    );
    $home->showinflatnavigation = true;

    if (isloggedin() && !isguestuser()) {
        $my = $nav->add(
            get_string('nav_my_cabinet', 'local_kaznu'),
            new moodle_url('/local/kaznu/my.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_kaznu_my_cabinet',
            new pix_icon('i/user', '')
        );
        $my->showinflatnavigation = true;
        if ($oncabinet) {
            $my->make_active();
        }

        $chat = $nav->add(
            get_string('nav_ai_chat', 'local_kaznu'),
            new moodle_url('/local/kaznu/chat.php'),
            navigation_node::TYPE_CUSTOM,
            null,
            'local_kaznu_ai_chat',
            new pix_icon('i/messagecontent', '')
        );
        $chat->showinflatnavigation = true;
        if (!empty($PAGE) && $PAGE->url && str_contains($PAGE->url->get_path(false), '/local/kaznu/chat.php')) {
            $chat->make_active();
        }
    }

    $node = $nav->add(
        get_string('navpay', 'local_kaznu'),
        new moodle_url('/local/kaznu/pay.php'),
        navigation_node::TYPE_CUSTOM,
        null,
        'local_kaznu_pay',
        new pix_icon('i/course', '')
    );
    $node->showinflatnavigation = true;
}
