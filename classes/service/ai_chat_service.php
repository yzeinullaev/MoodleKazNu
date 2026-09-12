<?php
namespace local_kaznu\service;

use local_kaznu\access\portal_policy;

defined('MOODLE_INTERNAL') || die();

/**
 * Student AI assistant: builds personal context and calls OpenAI Chat Completions.
 */
class ai_chat_service {
    public const MAX_MESSAGE_CHARS = 2000;
    public const MAX_HISTORY = 12;

    /**
     * @param array<int,array{role:string,content:string}> $history
     * @return array{ok:bool,reply?:string,error?:string}
     */
    public function chat(\stdClass $user, string $message, array $history = []): array {
        try {
            $message = trim($message);
            if ($message === '') {
                return ['ok' => false, 'error' => 'empty'];
            }
            if (\core_text::strlen($message) > self::MAX_MESSAGE_CHARS) {
                return ['ok' => false, 'error' => 'too_long'];
            }

            $apikey = trim((string) get_config('local_kaznu', 'openai_api_key'));
            if ($apikey === '') {
                return ['ok' => false, 'error' => 'not_configured'];
            }

            if (!(int) get_config('local_kaznu', 'ai_chat_enabled')) {
                return ['ok' => false, 'error' => 'disabled'];
            }

            $model = trim((string) (get_config('local_kaznu', 'openai_model') ?: 'gpt-4o-mini'));
            $system = $this->build_system_prompt($user);
            $messages = [['role' => 'system', 'content' => $system]];

            foreach ($this->sanitize_history($history) as $item) {
                $messages[] = $item;
            }
            $messages[] = ['role' => 'user', 'content' => $message];

            return $this->call_openai($apikey, $model, $messages);
        } catch (\Throwable $e) {
            debugging('local_kaznu ai_chat_service: ' . $e->getMessage(), DEBUG_DEVELOPER);
            return ['ok' => false, 'error' => 'exception'];
        }
    }

    public function build_system_prompt(\stdClass $user): string {
        try {
            $context = $this->build_student_context($user);
        } catch (\Throwable $e) {
            debugging('local_kaznu ai context: ' . $e->getMessage(), DEBUG_DEVELOPER);
            $firstname = trim((string) $user->firstname);
            if ($firstname === '') {
                $firstname = fullname($user);
            }
            $context = [
                'firstname' => $firstname,
                'fullname' => fullname($user),
                'text' => "Имя: {$firstname}\nКонтекст кабинета временно недоступен.",
            ];
        }

        $firstname = $context['firstname'];
        $fullname = $context['fullname'];

        return implode("\n", [
            'Ты — помощник студента образовательной платформы Farabi School (КазНУ / Farabi IBS).',
            'Отвечай на языке пользователя (обычно русский или казахский). Будь кратким, полезным и вежливым.',
            "Обращайся к студенту по имени: «{$firstname}». Полное имя: «{$fullname}».",
            'Используй только данные из блока КОНТЕКСТ_СТУДЕНТА ниже. Не выдумывай оценки, пары и курсы.',
            'Если данных нет в контексте — честно скажи, что сведений пока нет, и предложи обновить кабинет.',
            'Не проси и не раскрывай пароли, токены, ИИН и банковские данные.',
            'Не выполняй действия в Moodle за пользователя — только консультируй.',
            '',
            'КОНТЕКСТ_СТУДЕНТА:',
            $context['text'],
        ]);
    }

    /**
     * @return array{firstname:string,fullname:string,text:string}
     */
    public function build_student_context(\stdClass $user): array {
        global $CFG;
        require_once($CFG->libdir . '/enrollib.php');

        $userid = (int) $user->id;
        $fullname = fullname($user);
        $firstname = trim((string) $user->firstname);
        if ($firstname === '') {
            $firstname = $fullname;
        }

        $parts = [];
        $parts[] = "Имя для обращения: {$firstname}";
        $parts[] = "Полное имя: {$fullname}";

        $islinked = false;
        try {
            $islinked = class_exists(portal_policy::class)
                && portal_policy::can_view_univer_data($user);
        } catch (\Throwable $e) {
            $islinked = false;
        }
        // Fallback: permanent Univer row is enough for AI context.
        if (!$islinked) {
            global $DB;
            $islinked = $DB->record_exists('local_kaznu_univer_user', ['userid' => $userid]);
        }

        $overview = ['profile' => null, 'subjects' => [], 'year' => 0, 'semester' => 0, 'period_source' => ''];
        $schedule = ['lessons' => [], 'exams' => [], 'midterms' => [], 'year' => 0, 'semester' => 0];
        $grades = ['assessments' => [], 'year' => 0, 'semester' => 0];
        try {
            $dash = new student_dashboard_service();
            $overview = $dash->get_cabinet($userid, 'overview');
            // Always load academic modules when linked — AI needs full picture.
            $schedule = $dash->get_cabinet($userid, 'schedule');
            $grades = $dash->get_cabinet($userid, 'grades');
        } catch (\Throwable $e) {
            debugging('local_kaznu ai cabinet: ' . $e->getMessage(), DEBUG_DEVELOPER);
        }

        $profile = $overview['profile'] ?? null;
        $year = (int) ($overview['year'] ?? 0);
        $sem = (int) ($overview['semester'] ?? 0);
        $periodsource = (string) ($overview['period_source'] ?? '');
        if ($profile) {
            $parts[] = 'Профиль Univer: связан';
            foreach (['faculty' => 'Факультет', 'speciality' => 'Специальность', 'group_name' => 'Группа', 'course_number' => 'Курс обучения'] as $field => $label) {
                if (!empty($profile->{$field})) {
                    $parts[] = $label . ': ' . (string) $profile->{$field};
                }
            }
            $profileyear = (int) ($profile->current_year ?? 0);
            $profilesem = (int) ($profile->current_semester ?? 0);
            if ($profileyear > 0) {
                $parts[] = "Текущий период Univer (профиль): {$profileyear} / {$profilesem}";
            }
            if ($year > 0) {
                $parts[] = "Период данных для ответов: {$year} / {$sem}" .
                    ($periodsource !== '' ? " (источник: {$periodsource})" : '');
            }
        } else {
            $parts[] = 'Профиль Univer: не связан. Для расписания и дисциплин нужен вход через Univer.';
        }

        $subjects = $overview['subjects'] ?? [];
        if ($subjects) {
            $parts[] = 'Дисциплины Univer:';
            $n = 0;
            foreach ($subjects as $row) {
                if (++$n > 25) {
                    $parts[] = '…';
                    break;
                }
                $parts[] = '- ' . (string) ($row->subjectname ?? '');
            }
        } else {
            $parts[] = 'Дисциплины Univer: нет данных в кэше.';
        }

        try {
            $mycourses = enrol_get_users_courses($userid, true, 'id,fullname,shortname,visible');
            if ($mycourses) {
                $parts[] = 'Онлайн-курсы Moodle:';
                foreach ($mycourses as $c) {
                    if (!(int) $c->visible) {
                        continue;
                    }
                    $parts[] = '- ' . format_string($c->fullname) . ' (' . $c->shortname . ')';
                }
            } else {
                $parts[] = 'Онлайн-курсы Moodle: пока нет.';
            }
        } catch (\Throwable $e) {
            $parts[] = 'Онлайн-курсы Moodle: недоступны.';
        }

        $lessons = $schedule['lessons'] ?? [];
        if ($lessons) {
            $parts[] = 'Расписание занятий:';
            $n = 0;
            foreach ($lessons as $r) {
                if (++$n > 30) {
                    $parts[] = '…';
                    break;
                }
                $room = trim((string) ($r->building ?? '') . ' ' . (string) ($r->audience ?? ''));
                $parts[] = sprintf(
                    '- день %d, %s–%s: %s (%s), ауд. %s',
                    (int) ($r->weekday ?? 0),
                    (string) ($r->timebegin ?? ''),
                    (string) ($r->timeend ?? ''),
                    (string) ($r->subjectname ?? ''),
                    (string) ($r->lesson_type ?? ''),
                    $room !== '' ? $room : '—'
                );
            }
        } else if ($islinked) {
            $parts[] = 'Расписание занятий: нет данных в кэше.';
        }

        $exams = $schedule['exams'] ?? [];
        if ($exams) {
            $parts[] = 'Экзамены:';
            $n = 0;
            foreach ($exams as $r) {
                if (++$n > 20) {
                    break;
                }
                $date = !empty($r->eventdate) ? date('d.m.Y', (int) $r->eventdate) : '';
                $parts[] = sprintf(
                    '- %s %s: %s',
                    $date,
                    (string) ($r->eventtime ?? $r->timebegin ?? ''),
                    (string) ($r->subjectname ?? '')
                );
            }
        }

        $assessments = $grades['assessments'] ?? [];
        if ($assessments) {
            $parts[] = 'Оценки:';
            $n = 0;
            foreach ($assessments as $r) {
                if (++$n > 25) {
                    break;
                }
                $parts[] = sprintf(
                    '- %s: итого=%s, GPA=%s',
                    (string) ($r->subjectname ?? ''),
                    (string) ($r->totalres ?? ''),
                    (string) ($r->gpa ?? '')
                );
            }
        }

        return [
            'firstname' => $firstname,
            'fullname' => $fullname,
            'text' => implode("\n", $parts),
        ];
    }

    /**
     * @param array<int,mixed> $history
     * @return array<int,array{role:string,content:string}>
     */
    private function sanitize_history(array $history): array {
        $out = [];
        foreach ($history as $item) {
            if (!is_array($item)) {
                continue;
            }
            $role = (string) ($item['role'] ?? '');
            $content = trim((string) ($item['content'] ?? ''));
            if (!in_array($role, ['user', 'assistant'], true) || $content === '') {
                continue;
            }
            if (\core_text::strlen($content) > self::MAX_MESSAGE_CHARS) {
                $content = \core_text::substr($content, 0, self::MAX_MESSAGE_CHARS);
            }
            $out[] = ['role' => $role, 'content' => $content];
        }
        if (count($out) > self::MAX_HISTORY) {
            $out = array_slice($out, -self::MAX_HISTORY);
        }
        return $out;
    }

    /**
     * @param array<int,array{role:string,content:string}> $messages
     * @return array{ok:bool,reply?:string,error?:string}
     */
    private function call_openai(string $apikey, string $model, array $messages): array {
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.4,
            'max_tokens' => 700,
        ], JSON_UNESCAPED_UNICODE);

        if ($payload === false) {
            return ['ok' => false, 'error' => 'encode'];
        }

        $raw = '';
        $http = 0;

        // Prefer native PHP cURL (reliable under Apache AJAX).
        if (function_exists('curl_init')) {
            $ch = curl_init('https://api.openai.com/v1/chat/completions');
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apikey,
                ],
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_TIMEOUT => 45,
                CURLOPT_CONNECTTIMEOUT => 10,
            ]);
            $raw = (string) curl_exec($ch);
            $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $cerr = curl_error($ch);
            curl_close($ch);
            if ($raw === '' && $cerr !== '') {
                debugging('local_kaznu AI curl error: ' . $cerr, DEBUG_DEVELOPER);
                return ['ok' => false, 'error' => 'provider'];
            }
        } else {
            // Fallback: Moodle curl helper.
            global $CFG;
            require_once($CFG->libdir . '/filelib.php');
            $curl = new \curl(['timeout' => 45, 'connecttimeout' => 10]);
            $curl->setHeader('Content-Type: application/json');
            $curl->setHeader('Authorization: Bearer ' . $apikey);
            $raw = (string) $curl->post('https://api.openai.com/v1/chat/completions', $payload);
            $info = $curl->get_info();
            $http = (int) ($info['http_code'] ?? 0);
        }

        if ($http < 200 || $http >= 300) {
            debugging('local_kaznu AI OpenAI HTTP ' . $http . ' body=' . substr($raw, 0, 200), DEBUG_DEVELOPER);
            return ['ok' => false, 'error' => 'provider'];
        }

        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return ['ok' => false, 'error' => 'parse'];
        }
        $reply = trim((string) ($data['choices'][0]['message']['content'] ?? ''));
        if ($reply === '') {
            return ['ok' => false, 'error' => 'empty_reply'];
        }
        return ['ok' => true, 'reply' => $reply];
    }
}
