<?php
/**
 * Legacy Univer login URL — redirect to unified form.
 *
 * @package local_kaznu
 */

require_once(__DIR__ . '/../../config.php');

// Keep compatible entry for bookmarks; no separate form (Phase 6B unified login).
$wants = optional_param('wantsurl', '', PARAM_LOCALURL);
$params = [];
if ($wants !== '' && class_exists(\local_kaznu\local\return_url::class)
        && \local_kaznu\local\return_url::is_local($wants)) {
    $params['wantsurl'] = $wants;
}
// Refresh handoff: still go to unified login (user re-auths with same form).
redirect(new moodle_url('/login/index.php', $params));
