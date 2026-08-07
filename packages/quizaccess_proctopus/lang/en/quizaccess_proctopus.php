<?php
defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Proctopus proctoring';
$string['proctopusrequired'] = 'Require Proctopus proctoring';
$string['proctopuswebsites'] = 'Websites allowed';
$string['proctopuslookaway'] = 'Detect looking away';
$string['proctopusmoveaway'] = 'Detect moving away';
$string['proctopusvoices'] = 'Detect voices';
$string['proctopusduration'] = 'Duration override (minutes in JWT payload)';
$string['proctopusduration_help'] = 'If set, used as legacy duration minutes. Otherwise quiz time limit (converted from seconds) or site default is used. Never fixed at 90.';
$string['proctopusmode'] = 'Proctoring mode';
$string['proctopusruledesc'] = 'This test requires proctoring before you can start.';
$string['must_start_proctoring'] = 'Proctoring is required. Open preparation first ({$a}).';
$string['privacy:metadata'] = 'Stores per-quiz proctoring settings only.';
