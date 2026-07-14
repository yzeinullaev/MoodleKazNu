<?php
/**
 * Public landing page — Farabi University / KazNU summer school demo.
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/kaznu/landing.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_pagetype('local-kaznu-landing');
$PAGE->set_title(get_string('landing_title', 'local_kaznu'));
$PAGE->add_body_class('local-kaznu-landing-body');

$course = local_kaznu_get_demo_course();
$payurl = new moodle_url('/local/kaznu/pay.php');
$loginurl = new moodle_url('/login/index.php');
$courseurl = $course
    ? new moodle_url('/course/view.php', ['id' => $course->id])
    : $payurl;
$loggedin = isloggedin() && !isguestuser();
$enrolled = $loggedin && $course && local_kaznu_is_enrolled((int) $USER->id);
$primaryurl = $enrolled ? $courseurl : $payurl;
$primarylabel = $enrolled
    ? get_string('landing_cta_course', 'local_kaznu')
    : get_string('landing_cta_enrol', 'local_kaznu');

$cssurl = new moodle_url('/local/kaznu/styles_landing.css', [
    'rev' => get_config('local_kaznu', 'version') ?: '1',
]);

// Standalone HTML — do not use $OUTPUT->standard_* (leaves %%ENDHTML%% placeholders).
echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo format_string(get_string('landing_title', 'local_kaznu')); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?php echo $cssurl->out(false); ?>">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body class="local-kaznu-landing-body">

<div class="kzn-landing">
    <header class="kzn-nav">
        <a class="kzn-brand" href="<?php echo (new moodle_url('/local/kaznu/landing.php'))->out(false); ?>">
            <span class="kzn-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" width="40" height="40" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M8 30c6-10 12-14 16-14s10 4 16 14" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    <path d="M14 28l6-8 4 5 5-7 5 10" stroke="currentColor" stroke-width="1.5" fill="none"/>
                    <circle cx="24" cy="14" r="2" fill="currentColor"/>
                </svg>
            </span>
            <span class="kzn-brand-text">
                <strong>Farabi University</strong>
                <em><?php echo get_string('landing_brand_sub', 'local_kaznu'); ?></em>
            </span>
        </a>
        <nav class="kzn-nav-links" aria-label="Main">
            <a href="#program"><?php echo get_string('landing_nav_program', 'local_kaznu'); ?></a>
            <a href="#path"><?php echo get_string('landing_nav_path', 'local_kaznu'); ?></a>
            <a href="#modules"><?php echo get_string('landing_nav_modules', 'local_kaznu'); ?></a>
            <?php if ($loggedin): ?>
                <a class="kzn-nav-btn" href="<?php echo $primaryurl->out(false); ?>"><?php echo $primarylabel; ?></a>
            <?php else: ?>
                <a href="<?php echo $loginurl->out(false); ?>"><?php echo get_string('login'); ?></a>
                <a class="kzn-nav-btn" href="<?php echo $payurl->out(false); ?>"><?php echo get_string('landing_cta_enrol', 'local_kaznu'); ?></a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="kzn-hero" aria-label="Hero">
        <div class="kzn-hero-sky" aria-hidden="true"></div>
        <div class="kzn-hero-mountains" aria-hidden="true">
            <svg viewBox="0 0 1440 420" preserveAspectRatio="xMidYMax slice" xmlns="http://www.w3.org/2000/svg">
                <path class="kzn-peak kzn-peak-far" d="M0 420V220L180 120l160 90 140-110 220 140 180-90 200 80 180-70 180 70v90H0z"/>
                <path class="kzn-peak kzn-peak-near" d="M0 420V300l220-80 180 70 200-100 240 90 260-70 340 90v0H0z"/>
            </svg>
        </div>
        <div class="kzn-hero-content">
            <p class="kzn-eyebrow"><?php echo get_string('landing_eyebrow', 'local_kaznu'); ?></p>
            <h1 class="kzn-logo-title"><?php echo get_string('landing_brand', 'local_kaznu'); ?></h1>
            <p class="kzn-lead"><?php echo get_string('landing_lead', 'local_kaznu'); ?></p>
            <div class="kzn-hero-actions">
                <a class="kzn-btn kzn-btn-gold" href="<?php echo $primaryurl->out(false); ?>"><?php echo $primarylabel; ?></a>
                <a class="kzn-btn kzn-btn-ghost" href="#program"><?php echo get_string('landing_cta_more', 'local_kaznu'); ?></a>
            </div>
        </div>
    </section>

    <section class="kzn-section kzn-program" id="program">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_program_title', 'local_kaznu'); ?></h2>
            <p class="kzn-section-lead"><?php echo get_string('landing_program_lead', 'local_kaznu'); ?></p>
            <div class="kzn-split">
                <div class="kzn-prose">
                    <p><?php echo get_string('landing_program_p1', 'local_kaznu'); ?></p>
                    <p><?php echo get_string('landing_program_p2', 'local_kaznu'); ?></p>
                    <ul class="kzn-checklist">
                        <li><?php echo get_string('landing_check1', 'local_kaznu'); ?></li>
                        <li><?php echo get_string('landing_check2', 'local_kaznu'); ?></li>
                        <li><?php echo get_string('landing_check3', 'local_kaznu'); ?></li>
                        <li><?php echo get_string('landing_check4', 'local_kaznu'); ?></li>
                    </ul>
                </div>
                <aside class="kzn-aside" aria-label="<?php echo get_string('landing_facts_title', 'local_kaznu'); ?>">
                    <h3><?php echo get_string('landing_facts_title', 'local_kaznu'); ?></h3>
                    <dl class="kzn-facts">
                        <div><dt><?php echo get_string('landing_fact1_label', 'local_kaznu'); ?></dt><dd><?php echo get_string('landing_fact1_value', 'local_kaznu'); ?></dd></div>
                        <div><dt><?php echo get_string('landing_fact2_label', 'local_kaznu'); ?></dt><dd><?php echo get_string('landing_fact2_value', 'local_kaznu'); ?></dd></div>
                        <div><dt><?php echo get_string('landing_fact3_label', 'local_kaznu'); ?></dt><dd><?php echo get_string('landing_fact3_value', 'local_kaznu'); ?></dd></div>
                        <div><dt><?php echo get_string('landing_fact4_label', 'local_kaznu'); ?></dt><dd><?php echo get_string('landing_fact4_value', 'local_kaznu'); ?></dd></div>
                    </dl>
                </aside>
            </div>
        </div>
    </section>

    <section class="kzn-section kzn-path" id="path">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_path_title', 'local_kaznu'); ?></h2>
            <p class="kzn-section-lead"><?php echo get_string('landing_path_lead', 'local_kaznu'); ?></p>
            <ol class="kzn-path-list">
                <li>
                    <span class="kzn-step-num">01</span>
                    <h3><?php echo get_string('landing_step1_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_step1_text', 'local_kaznu'); ?></p>
                </li>
                <li>
                    <span class="kzn-step-num">02</span>
                    <h3><?php echo get_string('landing_step2_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_step2_text', 'local_kaznu'); ?></p>
                </li>
                <li>
                    <span class="kzn-step-num">03</span>
                    <h3><?php echo get_string('landing_step3_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_step3_text', 'local_kaznu'); ?></p>
                </li>
            </ol>
        </div>
    </section>

    <section class="kzn-section kzn-modules" id="modules">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_modules_title', 'local_kaznu'); ?></h2>
            <p class="kzn-section-lead"><?php echo get_string('landing_modules_lead', 'local_kaznu'); ?></p>
            <div class="kzn-module-rail">
                <article>
                    <span>Модуль 1</span>
                    <h3><?php echo get_string('landing_mod1_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_mod1_text', 'local_kaznu'); ?></p>
                </article>
                <article>
                    <span>Модуль 2</span>
                    <h3><?php echo get_string('landing_mod2_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_mod2_text', 'local_kaznu'); ?></p>
                </article>
                <article>
                    <span>Модуль 3</span>
                    <h3><?php echo get_string('landing_mod3_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_mod3_text', 'local_kaznu'); ?></p>
                </article>
                <article class="kzn-exam">
                    <span>Экзамен</span>
                    <h3><?php echo get_string('landing_exam_title', 'local_kaznu'); ?></h3>
                    <p><?php echo get_string('landing_exam_text', 'local_kaznu'); ?></p>
                </article>
            </div>
        </div>
    </section>

    <section class="kzn-cta-band" id="enrol">
        <div class="kzn-wrap kzn-cta-inner">
            <div>
                <h2><?php echo get_string('landing_cta_title', 'local_kaznu'); ?></h2>
                <p><?php echo get_string('landing_cta_text', 'local_kaznu'); ?></p>
            </div>
            <a class="kzn-btn kzn-btn-gold kzn-btn-lg" href="<?php echo $payurl->out(false); ?>">
                <?php echo get_string('landing_cta_enrol', 'local_kaznu'); ?>
            </a>
        </div>
    </section>

    <footer class="kzn-footer">
        <div class="kzn-wrap kzn-footer-inner">
            <div>
                <strong>Әл-Фараби атындағы ҚазҰУ</strong>
                <p><?php echo get_string('landing_footer', 'local_kaznu'); ?></p>
            </div>
            <div class="kzn-footer-links">
                <a href="<?php echo $payurl->out(false); ?>"><?php echo get_string('navpay', 'local_kaznu'); ?></a>
                <a href="<?php echo $loginurl->out(false); ?>"><?php echo get_string('login'); ?></a>
                <a href="https://farabi.university/?lang=ru" rel="noopener" target="_blank">farabi.university</a>
            </div>
        </div>
    </footer>
</div>

</body>
</html>
