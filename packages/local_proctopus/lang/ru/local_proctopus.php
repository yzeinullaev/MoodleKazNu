<?php
defined('MOODLE_INTERNAL') || die();

// Russian student-facing strings for proctoring UX.
$string['pluginname'] = 'Прокторинг Proctopus (Farabi)';
$string['proctopus_disabled'] = 'Прокторинг временно недоступен.';
$string['proctopus_start_failed'] = 'Не удалось подключиться к системе прокторинга. Проверьте интернет и попробуйте ещё раз.';
$string['proctopus_not_configured'] = 'Прокторинг недоступен. Обратитесь к команде курса.';
$string['proctopus_invalid_return'] = 'Не удалось подтвердить прокторинг. Откройте тест снова.';

$string['prep_title'] = 'Подготовка к тесту';
$string['prep_subtitle'] = 'Перед началом проверим оборудование и подключение.';
$string['prep_back_quiz'] = 'К странице теста';
$string['prep_ready_banner'] = 'Проверка оборудования пройдена. Можно перейти к прокторингу.';
$string['prep_need_banner'] = 'Нужно устранить замечания и повторить проверку.';

$string['stepper_label'] = 'Этапы теста';
$string['step_prep'] = 'Проверка';
$string['step_proctoring'] = 'Прокторинг';
$string['step_test'] = 'Тест';
$string['step_done'] = 'Завершение';

$string['check_card_camera'] = 'Камера';
$string['check_card_camera_hint'] = 'Проверяем доступ к камере';
$string['check_card_mic'] = 'Микрофон';
$string['check_card_mic_hint'] = 'Проверяем доступ к микрофону';
$string['check_card_screen'] = 'Экран';
$string['check_card_screen_hint'] = 'Проверяем возможность демонстрации экрана';
$string['check_card_connection'] = 'Соединение';
$string['check_card_connection_hint'] = 'Проверяем подключение к системе прокторинга';
$string['check_card_extension'] = 'Прокторинг';
$string['check_card_extension_hint'] = 'Расширение Farabi Proctoring будет проверено на следующем шаге';

$string['check_status_pending'] = 'Ожидает проверки';
$string['check_status_checking'] = 'Проверяем…';
$string['check_status_success'] = 'Готово';
$string['check_status_warning'] = 'Требуется действие';
$string['check_status_error'] = 'Не удалось проверить';

$string['check_camera_ok'] = 'Камера доступна браузеру';
$string['check_camera_need'] = 'Разрешите доступ к камере в браузере.';
$string['check_camera_fail'] = 'Не удалось получить доступ к камере.';
$string['check_mic_ok'] = 'Микрофон доступен браузеру';
$string['check_mic_need'] = 'Разрешите доступ к микрофону.';
$string['check_mic_fail'] = 'Не удалось получить доступ к микрофону.';
$string['check_screen_ok'] = 'Демонстрация экрана поддерживается';
$string['check_screen_warn'] = 'Демонстрация экрана может быть недоступна в этом браузере';
$string['check_conn_ok'] = 'Подключение в порядке';
$string['check_conn_fail'] = 'Проверьте подключение к интернету';
$string['check_ext_pending'] = 'Будет проверено в прокторинге';
$string['check_ext_note'] = 'Полная проверка выполняется в Farabi Proctoring после перехода';

$string['cta_check_equipment'] = 'Проверить оборудование';
$string['cta_go_proctoring'] = 'Перейти к прокторингу';
$string['cta_start_test'] = 'Начать тест';
$string['cta_open_prep'] = 'Подготовиться к тесту';
$string['cta_retry_check'] = 'Повторить проверку';
$string['cta_retry_extension'] = 'Проверить расширение';
$string['cta_finish_test'] = 'Завершить тест';

$string['err_provider'] = 'Не удалось подключиться к системе прокторинга. Проверьте интернет и попробуйте ещё раз.';
$string['err_extension'] = 'Для прохождения теста необходимо расширение Farabi Proctoring.';
$string['err_https'] = 'Требуется защищённое соединение (HTTPS).';
$string['err_browser'] = 'Этот браузер не поддерживает необходимые возможности.';
$string['err_camera'] = 'Не удалось получить доступ к камере.';
$string['err_mic'] = 'Не удалось получить доступ к микрофону.';

$string['launch_title'] = 'Прокторинг';
$string['launch_intro'] = 'Пройдите подготовку, затем перейдите к прокторингу.';
$string['launch_cta'] = 'Перейти к прокторингу';
$string['launch_cta_full'] = 'Подготовиться к тесту';
$string['launch_continue_hint'] = 'Нажмите кнопку ниже, чтобы продолжить.';
$string['launch_stub_mode'] = 'Учебный режим: удалённое наблюдение не используется.';

$string['require_proctoring_title'] = 'Для этого теста требуется прокторинг';
$string['require_proctoring_body'] = 'Сначала откройте подготовку, проверьте оборудование и перейдите к прокторингу. Не закрывайте окно браузера во время теста.';
$string['status_unavailable'] = 'Прокторинг недоступен';
$string['status_stub_mode'] = 'Учебный режим';
$string['status_live_active'] = 'Прокторинг активен';
$string['status_required'] = 'Требуется прокторинг';
$string['status_stub_notice_student'] = 'Учебный режим — внешнее наблюдение не выполняется.';
$string['status_stub_notice_admin'] = 'Админ: живые вызовы выключены. Камера, микрофон и удалённое наблюдение ограничены.';
$string['check_integration'] = 'Локальная подготовка';
$string['check_camera_stub'] = 'Камера — в учебном режиме не используется';
$string['check_mic_stub'] = 'Микрофон — в учебном режиме не используется';
$string['check_live_skipped'] = 'Живое подключение прокторинга — пропущено';
$string['check_connection'] = 'Соединение';
$string['check_camera'] = 'Камера';
$string['check_mic'] = 'Микрофон';
$string['check_screen'] = 'Экран';

$string['complete_title'] = 'Тест завершён';
$string['complete_heading'] = 'Тест завершён';
$string['complete_answers_sent'] = 'Ответы отправлены';
$string['complete_attempt_saved'] = 'Попытка сохранена';
$string['complete_proctoring_ended'] = 'Прокторинг завершён';
$string['complete_pending_provider'] = 'Данные прокторинга обрабатываются';
$string['complete_grade'] = 'Результат: {$a->grade} / {$a->max}';
$string['complete_review'] = 'Открыть обзор';
$string['complete_back_course'] = 'Вернуться в курс';
$string['complete_back_dashboard'] = 'Мой кабинет';

$string['event_proctoring_exam_completed'] = 'Экзамен с прокторингом завершён';
$string['event_proctoring_result_received'] = 'Получен результат прокторинга';
$string['task_reconcile_sessions'] = 'Согласование зависших сессий Proctopus';
