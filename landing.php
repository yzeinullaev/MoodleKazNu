<?php
/**
 * Farabi IBS — MasterClass-style catalogue landing.
 */
require(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/locallib.php');

$PAGE->set_context(context_system::instance());
$PAGE->set_url(new moodle_url('/local/kaznu/landing.php'));
$PAGE->set_pagelayout('embedded');
$PAGE->set_pagetype('local-kaznu-landing');
$PAGE->set_title(get_string('landing_title', 'local_kaznu'));
$PAGE->add_body_class('local-kaznu-landing-body');

local_kaznu_ensure_catalogue_visible();

$courses = local_kaznu_get_catalogue_courses(true);
$featured = local_kaznu_get_demo_course();
$payurl = new moodle_url('/local/kaznu/pay.php');
$loginurl = new moodle_url('/login/index.php');
$landingurl = new moodle_url('/local/kaznu/landing.php');
$loggedin = isloggedin() && !isguestuser();

$continue = [];
$shelves = [
    'summer' => [],
    'business' => [],
    'research' => [],
];
foreach ($courses as $c) {
    $shelves[local_kaznu_course_shelf($c)][] = $c;
    if ($loggedin && local_kaznu_user_enrolled_in((int) $USER->id, (int) $c->id)) {
        $continue[] = $c;
    }
}

$xp = null;
$xpprogress = null;
$board = local_kaznu_leaderboard(8);
if ($loggedin) {
    $xp = local_kaznu_get_xp((int) $USER->id);
    $xpprogress = local_kaznu_xp_progress($xp);
}

$cssurl = new moodle_url('/local/kaznu/styles_landing.css', [
    'rev' => get_config('local_kaznu', 'version') ?: '2026072201',
]);

/**
 * Render one course tile.
 */
$render_tile = function (stdClass $c, bool $enrolled = false) use ($payurl, $loggedin, $USER): string {
    $hub = new moodle_url('/local/kaznu/course.php', ['id' => $c->id]);
    $accent = local_kaznu_course_accent($c);
    $ispaid = ($c->shortname === LOCAL_KAZNU_COURSE_SHORTNAME);
    $href = $hub->out(false);
    $cta = $ispaid ? get_string('landing_cta_enrol', 'local_kaznu') : get_string('landing_cta_open', 'local_kaznu');
    if ($enrolled && $loggedin) {
        $href = local_kaznu_resume_url($c, (int) $USER->id)->out(false);
        $cta = local_kaznu_resume_label($c, (int) $USER->id);
    } else if ($ispaid && !$enrolled) {
        $href = $payurl->out(false);
        $cta = get_string('landing_cta_enrol', 'local_kaznu');
    }
    $name = format_string($c->fullname);
    $short = s($c->shortname);
    return '<a class="kzn-tile kzn-accent-' . $accent . ($enrolled ? ' is-enrolled' : '') . '" href="' . $href . '">'
        . '<div class="kzn-tile-art" aria-hidden="true"></div>'
        . '<div class="kzn-tile-body">'
        . '<span class="kzn-tile-meta">' . $short . '</span>'
        . '<h3>' . $name . '</h3>'
        . '<span class="kzn-tile-cta">' . $cta . '</span>'
        . '</div></a>';
};

echo $OUTPUT->doctype();
?>
<html <?php echo $OUTPUT->htmlattributes(); ?>>
<head>
    <title><?php echo format_string(get_string('landing_title', 'local_kaznu')); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:ital,wght@0,500;0,600;0,700;1,500&family=Manrope:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" href="<?php echo (new moodle_url('/local/kaznu/pix/favicon.svg'))->out(false); ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?php echo $cssurl->out(false); ?>">
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
</head>
<body class="local-kaznu-landing-body">

<div class="kzn-landing">
    <header class="kzn-nav">
        <a class="kzn-brand" href="<?php echo $landingurl->out(false); ?>">
            <span class="kzn-mark" aria-hidden="true">
                <svg viewBox="0 0 48 48" width="36" height="36" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <circle cx="24" cy="24" r="22" stroke="currentColor" stroke-width="1.4"/>
                    <path d="M24 8c-2 6-8 10-8 18 0 6 3.5 10 8 12 4.5-2 8-6 8-12 0-8-6-12-8-18z" stroke="currentColor" stroke-width="1.4" fill="none"/>
                    <path d="M18 22h12M20 28h8" stroke="currentColor" stroke-width="1.2"/>
                </svg>
            </span>
            <span class="kzn-brand-text">
                <strong><?php echo get_string('landing_brand', 'local_kaznu'); ?></strong>
                <em><?php echo get_string('landing_brand_sub', 'local_kaznu'); ?></em>
            </span>
        </a>
        <nav class="kzn-nav-links" aria-label="Main">
            <a href="#about"><?php echo get_string('landing_nav_program', 'local_kaznu'); ?></a>
            <a href="#courses"><?php echo get_string('landing_nav_courses', 'local_kaznu'); ?></a>
            <a href="#ranks"><?php echo get_string('landing_nav_ranks', 'local_kaznu'); ?></a>
            <span class="kzn-lang-switch" aria-label="Language">
                <a href="<?php echo (new moodle_url($landingurl, ['lang' => 'kk']))->out(false); ?>">Қаз</a>
                <a href="<?php echo (new moodle_url($landingurl, ['lang' => 'ru']))->out(false); ?>">Рус</a>
                <a href="<?php echo (new moodle_url($landingurl, ['lang' => 'en']))->out(false); ?>">Eng</a>
                <a href="<?php echo (new moodle_url($landingurl, ['lang' => 'zh_cn']))->out(false); ?>">中文</a>
            </span>
            <?php if ($loggedin): ?>
                <?php if ($xp): ?>
                    <span class="kzn-nav-xp" title="<?php echo s($xpprogress['title']); ?>">
                        Lv <?php echo (int) $xp->level; ?> · <?php echo (int) $xp->xp; ?> XP
                    </span>
                <?php endif; ?>
                <a class="kzn-nav-btn" href="#courses"><?php echo get_string('landing_nav_courses', 'local_kaznu'); ?></a>
            <?php else: ?>
                <a href="<?php echo $loginurl->out(false); ?>"><?php echo get_string('login'); ?></a>
                <a class="kzn-nav-btn" href="<?php echo $payurl->out(false); ?>"><?php echo get_string('landing_cta_enrol', 'local_kaznu'); ?></a>
            <?php endif; ?>
        </nav>
    </header>

    <section class="kzn-hero" aria-label="Hero">
        <div class="kzn-hero-wash" aria-hidden="true"></div>
        <div class="kzn-hero-content">
            <p class="kzn-eyebrow"><?php echo get_string('landing_eyebrow', 'local_kaznu'); ?></p>
            <p class="kzn-slogan"><?php echo get_string('landing_slogan', 'local_kaznu'); ?></p>
            <h1 class="kzn-logo-title"><?php echo get_string('landing_brand', 'local_kaznu'); ?></h1>
            <p class="kzn-lead"><?php echo get_string('landing_lead', 'local_kaznu'); ?></p>
            <div class="kzn-hero-actions">
                <a class="kzn-btn kzn-btn-accent" href="#courses"><?php echo get_string('landing_cta_browse', 'local_kaznu'); ?></a>
                <a class="kzn-btn kzn-btn-ghost" href="<?php echo $payurl->out(false); ?>"><?php echo get_string('landing_cta_enrol', 'local_kaznu'); ?></a>
            </div>
            <?php if ($loggedin && $xpprogress): ?>
                <div class="kzn-hero-xp">
                    <div class="kzn-xp-meta">
                        <strong><?php echo s($xpprogress['title']); ?></strong>
                        <span>Lv <?php echo (int) $xp->level; ?> · <?php echo (int) $xp->xp; ?> XP</span>
                    </div>
                    <div class="kzn-xp-bar" role="progressbar" aria-valuenow="<?php echo $xpprogress['pct']; ?>" aria-valuemin="0" aria-valuemax="100">
                        <span style="width:<?php echo $xpprogress['pct']; ?>%"></span>
                    </div>
                    <p class="kzn-xp-hint"><?php echo get_string('xp_to_next', 'local_kaznu', LOCAL_KAZNU_XP_PER_LEVEL - $xpprogress['into']); ?></p>
                </div>
            <?php endif; ?>
        </div>
        <?php if ($featured): ?>
            <?php
            $fenrolled = $loggedin && local_kaznu_user_enrolled_in((int) ($USER->id ?? 0), (int) $featured->id);
            $fhref = $fenrolled
                ? local_kaznu_resume_url($featured, (int) $USER->id)->out(false)
                : $payurl->out(false);
            $flabel = $fenrolled
                ? local_kaznu_resume_label($featured, (int) $USER->id)
                : get_string('landing_cta_enrol', 'local_kaznu');
            ?>
            <a class="kzn-hero-feature" href="<?php echo $fhref; ?>">
                <span class="kzn-hero-feature-label"><?php echo get_string('landing_featured', 'local_kaznu'); ?></span>
                <strong><?php echo format_string($featured->fullname); ?></strong>
                <em><?php echo $flabel; ?> →</em>
            </a>
        <?php endif; ?>
    </section>

    <section class="kzn-facts" aria-label="<?php echo get_string('landing_facts_title', 'local_kaznu'); ?>">
        <div class="kzn-wrap kzn-facts-grid">
            <?php for ($fi = 1; $fi <= 4; $fi++): ?>
                <div class="kzn-fact">
                    <strong><?php echo get_string('landing_fact' . $fi . '_value', 'local_kaznu'); ?></strong>
                    <span><?php echo get_string('landing_fact' . $fi . '_label', 'local_kaznu'); ?></span>
                </div>
            <?php endfor; ?>
        </div>
    </section>

    <section class="kzn-section kzn-about" id="about">
        <div class="kzn-wrap kzn-about-grid">
            <div>
                <h2><?php echo get_string('landing_program_title', 'local_kaznu'); ?></h2>
                <p class="kzn-section-lead"><?php echo get_string('landing_program_lead', 'local_kaznu'); ?></p>
                <p><?php echo get_string('landing_program_p1', 'local_kaznu'); ?></p>
                <p><?php echo get_string('landing_program_p2', 'local_kaznu'); ?></p>
            </div>
            <ul class="kzn-checks">
                <li><?php echo get_string('landing_check1', 'local_kaznu'); ?></li>
                <li><?php echo get_string('landing_check2', 'local_kaznu'); ?></li>
                <li><?php echo get_string('landing_check3', 'local_kaznu'); ?></li>
                <li><?php echo get_string('landing_check4', 'local_kaznu'); ?></li>
            </ul>
        </div>
    </section>

    <?php if ($continue): ?>
    <section class="kzn-shelf-section kzn-continue" id="continue">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_shelf_continue', 'local_kaznu'); ?></h2>
            <div class="kzn-rail">
                <?php foreach ($continue as $c): echo $render_tile($c, true); endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="kzn-shelf-section" id="courses">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_shelf_all', 'local_kaznu'); ?></h2>
            <p class="kzn-section-lead"><?php echo get_string('landing_shelf_all_lead', 'local_kaznu'); ?></p>
            <div class="kzn-rail">
                <?php
                foreach ($courses as $c) {
                    $en = $loggedin && local_kaznu_user_enrolled_in((int) $USER->id, (int) $c->id);
                    echo $render_tile($c, $en);
                }
                ?>
            </div>
        </div>
    </section>

    <?php if ($shelves['summer']): ?>
    <section class="kzn-shelf-section">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_shelf_summer', 'local_kaznu'); ?></h2>
            <div class="kzn-rail">
                <?php foreach ($shelves['summer'] as $c):
                    $en = $loggedin && local_kaznu_user_enrolled_in((int) $USER->id, (int) $c->id);
                    echo $render_tile($c, $en);
                endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($shelves['business']): ?>
    <section class="kzn-shelf-section">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_shelf_business', 'local_kaznu'); ?></h2>
            <div class="kzn-rail">
                <?php foreach ($shelves['business'] as $c):
                    $en = $loggedin && local_kaznu_user_enrolled_in((int) $USER->id, (int) $c->id);
                    echo $render_tile($c, $en);
                endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($shelves['research']): ?>
    <section class="kzn-shelf-section">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_shelf_research', 'local_kaznu'); ?></h2>
            <div class="kzn-rail">
                <?php foreach ($shelves['research'] as $c):
                    $en = $loggedin && local_kaznu_user_enrolled_in((int) $USER->id, (int) $c->id);
                    echo $render_tile($c, $en);
                endforeach; ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <section class="kzn-section kzn-path" id="path">
        <div class="kzn-wrap">
            <h2><?php echo get_string('landing_path_title', 'local_kaznu'); ?></h2>
            <p class="kzn-section-lead"><?php echo get_string('landing_path_lead', 'local_kaznu'); ?></p>
            <ol class="kzn-steps">
                <?php for ($si = 1; $si <= 3; $si++): ?>
                    <li>
                        <span class="kzn-step-num"><?php echo sprintf('%02d', $si); ?></span>
                        <div>
                            <h3><?php echo get_string('landing_step' . $si . '_title', 'local_kaznu'); ?></h3>
                            <p><?php echo get_string('landing_step' . $si . '_text', 'local_kaznu'); ?></p>
                        </div>
                    </li>
                <?php endfor; ?>
            </ol>
        </div>
    </section>

    <section class="kzn-section kzn-ranks" id="ranks">
        <div class="kzn-wrap kzn-ranks-grid">
            <div>
                <h2><?php echo get_string('landing_ranks_title', 'local_kaznu'); ?></h2>
                <p class="kzn-section-lead"><?php echo get_string('landing_ranks_lead', 'local_kaznu'); ?></p>
                <details class="kzn-ranks-details">
                    <summary><?php echo get_string('dash_ranks_info', 'local_kaznu'); ?></summary>
                    <ul class="kzn-xp-rules">
                        <li><?php echo get_string('xp_rule_quiz', 'local_kaznu', LOCAL_KAZNU_XP_QUIZ_PASS); ?></li>
                        <li><?php echo get_string('xp_rule_perfect', 'local_kaznu', LOCAL_KAZNU_XP_QUIZ_PERFECT); ?></li>
                        <li><?php echo get_string('xp_rule_enrol', 'local_kaznu', LOCAL_KAZNU_XP_ENROL); ?></li>
                        <li><?php echo get_string('xp_rule_level', 'local_kaznu', LOCAL_KAZNU_XP_PER_LEVEL); ?></li>
                    </ul>
                </details>
            </div>
            <aside class="kzn-leaderboard" aria-label="<?php echo get_string('landing_leaderboard', 'local_kaznu'); ?>">
                <h3><?php echo get_string('landing_leaderboard', 'local_kaznu'); ?></h3>
                <?php if ($board): ?>
                    <ol>
                        <?php foreach ($board as $i => $row): ?>
                            <li>
                                <span class="kzn-lb-rank"><?php echo $i + 1; ?></span>
                                <span class="kzn-lb-name"><?php echo fullname($row); ?></span>
                                <span class="kzn-lb-xp"><?php echo (int) $row->xp; ?> XP · Lv <?php echo (int) $row->level; ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php else: ?>
                    <p class="kzn-muted"><?php echo get_string('landing_leaderboard_empty', 'local_kaznu'); ?></p>
                <?php endif; ?>
            </aside>
        </div>
    </section>

    <footer class="kzn-footer">
        <div class="kzn-wrap kzn-footer-inner">
            <div>
                <strong><?php echo get_string('landing_brand', 'local_kaznu'); ?></strong>
                <p><?php echo get_string('landing_footer', 'local_kaznu'); ?></p>
                <p class="kzn-footer-contact"><?php echo get_string('landing_footer_contact', 'local_kaznu'); ?></p>
            </div>
            <div class="kzn-footer-links">
                <a href="https://kaznu-mba.kz/" rel="noopener" target="_blank">kaznu-mba.kz</a>
                <a href="<?php echo $loginurl->out(false); ?>"><?php echo get_string('login'); ?></a>
                <a href="https://farabi.university/?lang=ru" rel="noopener" target="_blank">farabi.university</a>
            </div>
        </div>
    </footer>
</div>

</body>
</html>
