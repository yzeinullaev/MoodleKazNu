<?php
/**
 * MasterClass-style course landing — trailer hero, syllabus, enrol (QR / button) on same page.
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
$action = optional_param('action', '', PARAM_ALPHA);

$course = get_course($id);
if ((int) $course->id <= 1) {
    throw new moodle_exception('invalidcourseid');
}

$context = context_course::instance($course->id);
$PAGE->set_context($context);
$PAGE->set_url(new moodle_url('/local/kaznu/course.php', ['id' => $id]));
$PAGE->set_pagelayout('embedded');
$PAGE->set_pagetype('local-kaznu-course');
$PAGE->set_title(format_string($course->fullname));
$PAGE->add_body_class('local-kaznu-landing-body');

$loggedin = isloggedin() && !isguestuser();
$enrolled = $loggedin && local_kaznu_user_enrolled_in((int) $USER->id, (int) $course->id);
$ispaid = ($course->shortname === LOCAL_KAZNU_COURSE_SHORTNAME);

// Summer school is retired for students (admins can still open it).
if (local_kaznu_is_retired_course($course)) {
    $sys = context_system::instance();
    if (!has_capability('moodle/site:config', $sys)) {
        redirect(
            new moodle_url('/local/kaznu/landing.php'),
            get_string('course_retired', 'local_kaznu'),
            null,
            \core\output\notification::NOTIFY_INFO
        );
    }
}
$moodlecourse = new moodle_url('/course/view.php', ['id' => $course->id]);
$landingurl = new moodle_url('/local/kaznu/landing.php');
$loginurl = new moodle_url('/login/index.php', ['wantsurl' => $PAGE->url->out(false)]);
$myurl = new moodle_url('/my/');

// Free courses: enrol on this page (no payment).
if ($action === 'enrol' && !$ispaid) {
    require_login();
    require_sesskey();
    local_kaznu_enrol_user($course, (int) $USER->id, 'student');
    redirect(
        $moodlecourse,
        get_string('enrolsuccess_free', 'local_kaznu'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
}

$modinfo = get_fast_modinfo($course);
$sections = [];
$lessoncount = 0;
foreach ($modinfo->get_section_info_all() as $section) {
    if ((int) $section->section === 0) {
        continue;
    }
    $name = get_section_name($course, $section);
    $cms = [];
    if (!empty($modinfo->sections[$section->section])) {
        foreach ($modinfo->sections[$section->section] as $cmid) {
            $cm = $modinfo->cms[$cmid];
            if (!$cm->uservisible && !$cm->is_visible_on_course_page()) {
                continue;
            }
            $cms[] = $cm;
            $lessoncount++;
        }
    }
    $sections[] = (object) [
        'num' => (int) $section->section,
        'name' => $name,
        'cms' => $cms,
        'available' => $section->available,
    ];
}

$xp = null;
$xpprogress = null;
if ($loggedin) {
    $xp = local_kaznu_get_xp((int) $USER->id);
    $xpprogress = local_kaznu_xp_progress($xp);
}

$cssurl = new moodle_url('/local/kaznu/styles_landing.css', [
    'rev' => get_config('local_kaznu', 'version') ?: '2026072417',
]);
$accent = local_kaznu_course_accent($course);
$blurb = local_kaznu_course_blurb($course);
$tagline = local_kaznu_course_tagline($course);
$trailer = local_kaznu_render_course_trailer($course);
$price = get_config('local_kaznu', 'paymentprice') ?: '25 000 ₸';
$sectioncount = count($sections);
$meta = get_string('hub_meta', 'local_kaznu', (object) [
    'sections' => $sectioncount,
    'lessons' => $lessoncount,
]);

$confirmurl = local_kaznu_confirm_url((int) $course->id);
$loginforconfirm = new moodle_url('/login/index.php', ['wantsurl' => $confirmurl->out(false)]);
$payaction = $loggedin ? $confirmurl : $loginforconfirm;
$qrimg = 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . urlencode($payaction->out(false));

$enrolanchor = new moodle_url('/local/kaznu/course.php', ['id' => $id], 'enrol');
$freeenrol = new moodle_url('/local/kaznu/course.php', [
    'id' => $id,
    'action' => 'enrol',
    'sesskey' => sesskey(),
]);

if ($enrolled) {
    $primary = local_kaznu_resume_url($course, (int) $USER->id);
    $primarylabel = local_kaznu_resume_label($course, (int) $USER->id);
} else if ($ispaid) {
    $primary = $enrolanchor;
    $primarylabel = get_string('landing_cta_enrol', 'local_kaznu');
} else if ($loggedin) {
    $primary = $freeenrol;
    $primarylabel = get_string('landing_cta_enrol', 'local_kaznu');
} else {
    $primary = $loginurl;
    $primarylabel = get_string('login');
}

$primaryhref = $primary->out(false);

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo format_string($course->fullname); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo (new moodle_url('/local/kaznu/pix/favicon.svg'))->out(false); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo $cssurl->out(false); ?>">
</head>
<body class="local-kaznu-landing-body">

<div class="kzn-landing kzn-hub kzn-class">
    <header class="kzn-nav kzn-nav-solid" data-kaznu-nav>
        <a class="kzn-brand" href="<?php echo $landingurl->out(false); ?>">
            <span class="kzn-brand-text">
                <strong><?php echo get_string('landing_brand', 'local_kaznu'); ?></strong>
                <em><?php echo get_string('landing_slogan', 'local_kaznu'); ?></em>
            </span>
        </a>
        <button type="button" class="kzn-nav-toggle" aria-expanded="false" aria-controls="kzn-nav-menu"
            aria-label="<?php echo get_string('landing_nav_menu', 'local_kaznu'); ?>">
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
            <span aria-hidden="true"></span>
        </button>
        <nav id="kzn-nav-menu" class="kzn-nav-links" aria-label="Main">
            <a href="<?php echo $landingurl->out(false); ?>"><?php echo get_string('landing_nav_courses', 'local_kaznu'); ?></a>
            <?php if ($loggedin): ?>
                <a href="<?php echo $myurl->out(false); ?>"><?php echo get_string('dash_title', 'local_kaznu'); ?></a>
                <?php if ($xp): ?>
                    <span class="kzn-nav-xp">Lv <?php echo (int) $xp->level; ?> · <?php echo (int) $xp->xp; ?> XP</span>
                <?php endif; ?>
            <?php else: ?>
                <a href="<?php echo $loginurl->out(false); ?>"><?php echo get_string('login'); ?></a>
            <?php endif; ?>
            <a class="kzn-nav-btn" href="<?php echo s($primaryhref); ?>"><?php echo $primarylabel; ?></a>
            <?php echo local_kaznu_render_lang_dropdown($landingurl); ?>
        </nav>
    </header>

    <section class="kzn-class-hero kzn-accent-<?php echo $accent; ?>" aria-label="<?php echo s(format_string($course->fullname)); ?>">
        <div class="kzn-class-hero-visual<?php echo $trailer !== '' ? ' has-trailer' : ''; ?>">
            <?php if ($trailer !== ''): ?>
                <?php echo $trailer; ?>
            <?php else: ?>
                <div class="kzn-class-hero-glow"></div>
                <p class="kzn-class-hero-mark"><?php echo get_string('landing_brand', 'local_kaznu'); ?></p>
            <?php endif; ?>
        </div>
        <div class="kzn-class-hero-panel">
            <p class="kzn-class-eyebrow"><?php echo s($course->shortname); ?></p>
            <h1><?php echo format_string($course->fullname); ?></h1>
            <p class="kzn-class-tagline"><?php echo s($tagline); ?></p>
            <p class="kzn-class-blurb"><?php echo s($blurb); ?></p>
            <p class="kzn-class-meta">
                <?php echo s($meta); ?>
                · <a href="#syllabus"><?php echo get_string('hub_watch_plan', 'local_kaznu'); ?></a>
            </p>

            <div class="kzn-class-cta-box">
                <?php if ($enrolled): ?>
                    <p class="kzn-class-cta-note"><?php echo get_string('hub_already', 'local_kaznu'); ?></p>
                    <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="<?php echo $primary->out(false); ?>"><?php echo $primarylabel; ?></a>
                <?php elseif ($ispaid): ?>
                    <p class="kzn-class-cta-note"><?php echo get_string('hub_get_class', 'local_kaznu'); ?></p>
                    <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="#enrol"><?php echo get_string('landing_cta_enrol', 'local_kaznu'); ?></a>
                    <p class="kzn-class-price-hint"><?php echo get_string('hub_price_from', 'local_kaznu', $price); ?></p>
                <?php else: ?>
                    <p class="kzn-class-cta-note"><?php echo get_string('hub_get_free', 'local_kaznu'); ?></p>
                    <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="<?php echo s($primaryhref); ?>"><?php echo $primarylabel; ?></a>
                <?php endif; ?>
            </div>
        </div>
    </section>

    <section class="kzn-section kzn-class-skills" id="skills">
        <div class="kzn-wrap">
            <h2><?php echo get_string('hub_skills_title', 'local_kaznu'); ?></h2>
            <ul class="kzn-class-skills-grid">
                <li><?php echo get_string('featvideo', 'local_kaznu'); ?></li>
                <li><?php echo get_string('feataudio', 'local_kaznu'); ?></li>
                <li><?php echo get_string('feattests', 'local_kaznu'); ?></li>
                <li><?php echo get_string('featcert', 'local_kaznu'); ?></li>
            </ul>
        </div>
    </section>

    <section class="kzn-section" id="syllabus">
        <div class="kzn-wrap">
            <h2><?php echo get_string('hub_syllabus', 'local_kaznu'); ?></h2>
            <ol class="kzn-syllabus">
                <?php foreach ($sections as $sec): ?>
                    <li class="<?php echo $sec->available ? '' : 'is-locked'; ?>">
                        <div class="kzn-syl-head">
                            <span class="kzn-step-num"><?php echo sprintf('%02d', $sec->num); ?></span>
                            <h3><?php echo format_string($sec->name); ?></h3>
                            <?php if (!$sec->available): ?>
                                <span class="kzn-lock"><?php echo get_string('hub_locked', 'local_kaznu'); ?></span>
                            <?php endif; ?>
                        </div>
                        <?php if ($sec->cms): ?>
                            <ul class="kzn-syl-acts">
                                <?php foreach ($sec->cms as $cm): ?>
                                    <li>
                                        <?php if ($enrolled && $cm->uservisible): ?>
                                            <a href="<?php echo $cm->url ? $cm->url->out(false) : $moodlecourse->out(false); ?>">
                                                <?php echo format_string($cm->name); ?>
                                            </a>
                                        <?php else: ?>
                                            <span><?php echo format_string($cm->name); ?></span>
                                        <?php endif; ?>
                                        <em><?php echo $cm->get_module_type_name(); ?></em>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ol>
        </div>
    </section>

    <?php if ($ispaid && !$enrolled): ?>
        <section class="kzn-section kzn-class-enrol" id="enrol">
            <div class="kzn-wrap kzn-class-enrol-inner">
                <div class="kzn-class-enrol-copy">
                    <p class="kzn-eyebrow"><?php echo get_string('paybadge', 'local_kaznu'); ?></p>
                    <h2><?php echo get_string('hub_enrol_title', 'local_kaznu'); ?></h2>
                    <p><?php echo get_string('payintro', 'local_kaznu'); ?></p>
                    <ol class="kzn-class-enrol-steps">
                        <li><?php echo get_string('paystep1', 'local_kaznu'); ?></li>
                        <li><?php echo get_string('paystep2', 'local_kaznu'); ?></li>
                        <li><?php echo get_string('paystep3', 'local_kaznu'); ?></li>
                    </ol>
                </div>
                <div class="kzn-class-enrol-pay">
                    <div class="kzn-class-enrol-price"><?php echo s($price); ?></div>
                    <p class="kzn-muted"><?php echo get_string('paydesc', 'local_kaznu'); ?></p>
                    <a class="kzn-class-qr" href="<?php echo $payaction->out(false); ?>" title="<?php echo get_string('paybutton', 'local_kaznu'); ?>">
                        <img src="<?php echo s($qrimg); ?>" width="200" height="200" alt="<?php echo get_string('qralt', 'local_kaznu'); ?>">
                    </a>
                    <p class="kzn-muted"><?php echo get_string('qrhint', 'local_kaznu'); ?></p>
                    <?php if (!$loggedin): ?>
                        <p class="kzn-muted"><?php echo get_string('payqrlogin', 'local_kaznu'); ?></p>
                    <?php endif; ?>
                    <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="<?php echo $payaction->out(false); ?>">
                        <?php echo $loggedin ? get_string('paybutton', 'local_kaznu') : get_string('payloginbutton', 'local_kaznu'); ?>
                    </a>
                </div>
            </div>
        </section>
    <?php elseif (!$enrolled): ?>
        <section class="kzn-section kzn-class-enrol kzn-class-enrol-free" id="enrol">
            <div class="kzn-wrap kzn-hub-cta">
                <h2><?php echo get_string('hub_enrol_title', 'local_kaznu'); ?></h2>
                <p><?php echo get_string('hub_get_free', 'local_kaznu'); ?></p>
                <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="<?php echo s($primaryhref); ?>"><?php echo $primarylabel; ?></a>
            </div>
        </section>
    <?php else: ?>
        <section class="kzn-section">
            <div class="kzn-wrap kzn-hub-cta">
                <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="<?php echo $primary->out(false); ?>"><?php echo $primarylabel; ?></a>
            </div>
        </section>
    <?php endif; ?>

    <footer class="kzn-footer">
        <div class="kzn-wrap kzn-footer-inner">
            <div>
                <strong><?php echo get_string('landing_brand', 'local_kaznu'); ?></strong>
                <p><?php echo get_string('landing_slogan', 'local_kaznu'); ?></p>
            </div>
            <div class="kzn-footer-links">
                <a href="<?php echo $landingurl->out(false); ?>"><?php echo get_string('landing_nav_courses', 'local_kaznu'); ?></a>
            </div>
        </div>
    </footer>
</div>
<script>
(function () {
    document.querySelectorAll('[data-kaznu-nav]').forEach(function (nav) {
        var toggle = nav.querySelector('.kzn-nav-toggle');
        var menu = nav.querySelector('.kzn-nav-links');
        if (!toggle || !menu) return;
        var setOpen = function (open) {
            nav.classList.toggle('is-open', open);
            toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
            document.body.classList.toggle('kzn-nav-lock', open);
        };
        toggle.addEventListener('click', function (e) {
            e.stopPropagation();
            setOpen(!nav.classList.contains('is-open'));
        });
        menu.querySelectorAll('a[href]:not(.kzn-lang-item)').forEach(function (link) {
            link.addEventListener('click', function () { setOpen(false); });
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') setOpen(false);
        });
        document.addEventListener('click', function (e) {
            if (!nav.contains(e.target)) setOpen(false);
        });
    });
    document.querySelectorAll('[data-kaznu-lang]').forEach(function (root) {
        var btn = root.querySelector('.kzn-lang-btn');
        var menu = root.querySelector('.kzn-lang-menu');
        if (!btn || !menu) return;
        btn.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = btn.getAttribute('aria-expanded') === 'true';
            btn.setAttribute('aria-expanded', open ? 'false' : 'true');
            menu.hidden = open;
        });
        document.addEventListener('click', function () {
            btn.setAttribute('aria-expanded', 'false');
            menu.hidden = true;
        });
    });
})();
</script>
</body>
</html>
