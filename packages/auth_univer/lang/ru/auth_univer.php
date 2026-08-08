<?php
// This file is part of Moodle - http://moodle.org/

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'Farabi School (единый вход)';
$string['auth_univerdescription'] = 'Сначала Univer API, затем локальный вход Moodle при неверных данных. Одна форма без выбора способа входа.';
$string['settings_heading'] = 'Единый вход';
$string['settings_desc'] = 'Учётные данные сначала проверяются в Univer. Только при неверном логине/пароле — локальная проверка Moodle. Ошибки сопоставления не допускают fallback. Пароли Univer-пользователей не хранятся.';
$string['auth_univer_invalidlogin'] = 'Неверный логин или пароль.';
$string['auth_univer_unavailable'] = 'Вход временно недоступен. Попробуйте позже.';
$string['auth_univer_rate_limited'] = 'Слишком много неудачных попыток. Попробуйте позже.';
$string['auth_univer_disabled'] = 'Вход временно отключён. Обратитесь в поддержку.';
$string['auth_univer_faculty_denied'] = 'Доступ к платформе предоставляется студентам Бизнес-школы.';
