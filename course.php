<?php
/**
 * MasterClass-style course hub — trailer, syllabus, continue, XP.
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$id = required_param('id', PARAM_INT);
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
$payurl = new moodle_url('/local/kaznu/pay.php');
$moodlecourse = new moodle_url('/course/view.php', ['id' => $course->id]);
$landingurl = new moodle_url('/local/kaznu/landing.php');
$loginurl = new moodle_url('/login/index.php', ['wantsurl' => $PAGE->url->out(false)]);

$modinfo = get_fast_modinfo($course);
$sections = [];
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
    'rev' => get_config('local_kaznu', 'version') ?: '2026072201',
]);
$accent = local_kaznu_course_accent($course);

$primary = $enrolled
    ? local_kaznu_resume_url($course, (int) $USER->id)
    : ($ispaid ? $payurl : $loginurl);
$primarylabel = $enrolled
    ? local_kaznu_resume_label($course, (int) $USER->id)
    : ($ispaid ? get_string('landing_cta_enrol', 'local_kaznu') : get_string('login'));

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

<div class="kzn-landing kzn-hub">
    <header class="kzn-nav kzn-nav-solid">
        <a class="kzn-brand" href="<?php echo $landingurl->out(false); ?>">
            <span class="kzn-brand-text">
                <strong><?php echo get_string('landing_brand', 'local_kaznu'); ?></strong>
                <em><?php echo get_string('landing_brand_sub', 'local_kaznu'); ?></em>
            </span>
        </a>
        <nav class="kzn-nav-links">
            <a href="<?php echo $landingurl->out(false); ?>#courses"><?php echo get_string('landing_nav_courses', 'local_kaznu'); ?></a>
            <?php if ($loggedin && $xp): ?>
                <span class="kzn-nav-xp">Lv <?php echo (int) $xp->level; ?> · <?php echo (int) $xp->xp; ?> XP</span>
            <?php endif; ?>
            <a class="kzn-nav-btn" href="<?php echo $primary->out(false); ?>"><?php echo $primarylabel; ?></a>
        </nav>
    </header>

    <section class="kzn-hub-hero kzn-accent-<?php echo $accent; ?>">
        <div class="kzn-wrap kzn-hub-hero-inner">
            <p class="kzn-eyebrow"><?php echo s($course->shortname); ?></p>
            <h1><?php echo format_string($course->fullname); ?></h1>
            <div class="kzn-hero-actions">
                <a class="kzn-btn kzn-btn-accent" href="<?php echo $primary->out(false); ?>"><?php echo $primarylabel; ?></a>
                <a class="kzn-btn kzn-btn-ghost" href="#syllabus"><?php echo get_string('hub_syllabus', 'local_kaznu'); ?></a>
            </div>
            <?php if ($xpprogress): ?>
                <div class="kzn-hero-xp">
                    <div class="kzn-xp-meta">
                        <strong><?php echo s($xpprogress['title']); ?></strong>
                        <span>Lv <?php echo (int) $xp->level; ?> · <?php echo (int) $xp->xp; ?> XP</span>
                    </div>
                    <div class="kzn-xp-bar"><span style="width:<?php echo $xpprogress['pct']; ?>%"></span></div>
                </div>
            <?php endif; ?>
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
            <div class="kzn-hub-cta">
                <a class="kzn-btn kzn-btn-accent kzn-btn-lg" href="<?php echo $primary->out(false); ?>"><?php echo $primarylabel; ?></a>
            </div>
        </div>
    </section>

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
</body>
</html>
