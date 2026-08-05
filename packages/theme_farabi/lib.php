<?php
// This file is part of Moodle - http://moodle.org/

/**
 * Theme callbacks for Farabi School.
 *
 * @package   theme_farabi
 * @copyright 2026 Farabi School
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Returns the main SCSS content.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_farabi_get_main_scss_content($theme) {
    global $CFG;

    $scss = '';
    $filename = !empty($theme->settings->preset) ? $theme->settings->preset : 'default.scss';
    $fs = get_file_storage();
    $context = context_system::instance();

    $scss .= file_get_contents($CFG->dirroot . '/theme/farabi/scss/pre.scss');

    if ($filename && ($presetfile = $fs->get_file($context->id, 'theme_farabi', 'preset', 0, '/', $filename))) {
        $scss .= $presetfile->get_content();
    } else if ($filename === 'plain.scss') {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/plain.scss');
    } else {
        $scss .= file_get_contents($CFG->dirroot . '/theme/boost/scss/preset/default.scss');
    }

    $scss .= file_get_contents($CFG->dirroot . '/theme/farabi/scss/post.scss');
    return $scss;
}

/**
 * Get SCSS to prepend.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_farabi_get_pre_scss($theme) {
    $scss = '';
    $configurable = [
        'brandcolor' => ['primary'],
    ];
    foreach ($configurable as $configkey => $targets) {
        $value = $theme->settings->{$configkey} ?? null;
        if (empty($value)) {
            continue;
        }
        array_map(function($target) use (&$scss, $value) {
            $scss .= '$' . $target . ': ' . $value . ";\n";
        }, (array) $targets);
    }
    if (!empty($theme->settings->scsspre)) {
        $scss .= $theme->settings->scsspre;
    }
    return $scss;
}

/**
 * Inject additional SCSS.
 *
 * @param theme_config $theme
 * @return string
 */
function theme_farabi_get_extra_scss($theme) {
    $content = '';
    if (!empty($theme->settings->scss)) {
        $content .= $theme->settings->scss;
    }
    return $content;
}

/**
 * Get compiled css.
 *
 * @return string
 */
function theme_farabi_get_precompiled_css() {
    global $CFG;
    $path = $CFG->dirroot . '/theme/farabi/style/moodle.css';
    return is_readable($path) ? file_get_contents($path) : '';
}

/**
 * Serves any files associated with the theme settings.
 *
 * @param stdClass $course
 * @param stdClass $cm
 * @param context $context
 * @param string $filearea
 * @param array $args
 * @param bool $forcedownload
 * @param array $options
 * @return bool
 */
function theme_farabi_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options = []) {
    if ($context->contextlevel == CONTEXT_SYSTEM && (
            $filearea === 'logo' ||
            $filearea === 'backgroundimage' ||
            $filearea === 'loginbackgroundimage')) {
        $theme = theme_config::load('farabi');
        if (!array_key_exists('cacheability', $options)) {
            $options['cacheability'] = 'public';
        }
        return $theme->setting_file_serve($filearea, $args, $forcedownload, $options);
    }
    send_file_not_found();
}
