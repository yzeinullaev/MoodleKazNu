<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . '/filelib.php');

/**
 * Per-user GPT-style chat with optional web search tool.
 */
class ai_chat_service {

    public static function list_conversations(int $userid): array {
        global $DB;
        $records = $DB->get_records_sql(
            "SELECT c.id, c.title, c.timemodified
               FROM {local_kaznu_conversation} c
              WHERE c.userid = ? AND c.deleted = 0
           ORDER BY c.timemodified DESC",
            [$userid],
            0,
            80
        );
        $list = [];
        foreach ($records as $r) {
            $list[] = [
                'id' => (int)$r->id,
                'title' => $r->title,
                'timemodified' => (int)$r->timemodified,
            ];
        }
        return $list;
    }

    public static function create_conversation(int $userid, string $title = ''): int {
        global $DB;
        $now = time();
        $rec = (object)[
            'userid' => $userid,
            'title' => $title !== '' ? $title : get_string('newchat', 'local_kaznu'),
            'timecreated' => $now,
            'timemodified' => $now,
            'deleted' => 0,
        ];
        return (int)$DB->insert_record('local_kaznu_conversation', $rec);
    }

    public static function delete_conversation(int $userid, int $conversationid): void {
        global $DB;
        $conv = $DB->get_record('local_kaznu_conversation', ['id' => $conversationid, 'userid' => $userid]);
        if (!$conv) {
            throw new \moodle_exception('invalidconversation', 'local_kaznu');
        }
        $conv->deleted = 1;
        $conv->timemodified = time();
        $DB->update_record('local_kaznu_conversation', $conv);
    }

    public static function get_messages(int $userid, int $conversationid): array {
        global $DB;
        self::assert_owner($userid, $conversationid);
        $rows = $DB->get_records('local_kaznu_message', ['conversationid' => $conversationid], 'timecreated ASC');
        $out = [];
        foreach ($rows as $r) {
            if (in_array($r->role, ['tool', 'system'], true)) {
                continue; // Hide internal messages in UI.
            }
            $out[] = [
                'id' => (int)$r->id,
                'role' => $r->role,
                'content' => $r->content,
                'timecreated' => (int)$r->timecreated,
            ];
        }
        return $out;
    }

    /**
     * @param bool $useweb User toggled web search for this message.
     * @return array{assistant:string, conversationid:int, used_web:bool}
     */
    public static function send_message(
        int $userid,
        int $conversationid,
        string $usertext,
        bool $useweb = false
    ): array {
        global $DB;

        $apikey = self::openai_api_key();
        if ($apikey === '') {
            throw new \moodle_exception('noapikey', 'local_kaznu');
        }

        if ($conversationid === 0) {
            $conversationid = self::create_conversation($userid, self::title_from_text($usertext));
        } else {
            self::assert_owner($userid, $conversationid);
        }

        $now = time();
        $DB->insert_record('local_kaznu_message', (object)[
            'conversationid' => $conversationid,
            'role' => 'user',
            'content' => $usertext,
            'metadata' => null,
            'timecreated' => $now,
        ]);

        $usedweb = false;
        $webenabled = (bool)get_config('local_kaznu', 'enable_web_search');
        if ($webenabled && $useweb) {
            $searchtext = web_search::search($usertext);
            if ($searchtext !== '') {
                $usedweb = true;
                $DB->insert_record('local_kaznu_message', (object)[
                    'conversationid' => $conversationid,
                    'role' => 'tool',
                    'content' => $searchtext,
                    'metadata' => json_encode(['type' => 'web_search']),
                    'timecreated' => $now + 1,
                ]);
            }
        }

        $messages = self::build_openai_messages($conversationid);
        $model = get_config('local_kaznu', 'openai_model') ?: 'gpt-4o-mini';
        $reply = self::call_openai($apikey, $model, $messages);

        $DB->insert_record('local_kaznu_message', (object)[
            'conversationid' => $conversationid,
            'role' => 'assistant',
            'content' => $reply,
            'metadata' => $usedweb ? json_encode(['web' => true]) : null,
            'timecreated' => time(),
        ]);

        $conv = $DB->get_record('local_kaznu_conversation', ['id' => $conversationid]);
        $conv->timemodified = time();
        if ($conv->title === get_string('newchat', 'local_kaznu')) {
            $conv->title = self::title_from_text($usertext);
        }
        $DB->update_record('local_kaznu_conversation', $conv);

        return [
            'assistant' => $reply,
            'conversationid' => $conversationid,
            'used_web' => $usedweb,
        ];
    }

    private static function openai_api_key(): string {
        $key = trim((string)get_config('local_kaznu', 'openai_apikey'));
        if ($key === '') {
            $key = trim((string)get_config('local_kaznu', 'openai_api_key'));
        }
        return $key;
    }

    private static function assert_owner(int $userid, int $conversationid): void {
        global $DB;
        if (!$DB->record_exists('local_kaznu_conversation', [
            'id' => $conversationid,
            'userid' => $userid,
            'deleted' => 0,
        ])) {
            throw new \moodle_exception('invalidconversation', 'local_kaznu');
        }
    }

    private static function title_from_text(string $text): string {
        $t = preg_replace('/\s+/u', ' ', trim($text));
        if (function_exists('mb_substr')) {
            return mb_substr($t, 0, 60) ?: get_string('newchat', 'local_kaznu');
        }
        return substr($t, 0, 60) ?: get_string('newchat', 'local_kaznu');
    }

    private static function build_openai_messages(int $conversationid): array {
        global $DB;
        $max = (int)get_config('local_kaznu', 'max_history');
        if ($max < 4) {
            $max = 40;
        }

        $system = 'Ты — полезный AI-ассистент Farabi International Business School для студентов. '
            . 'Отвечай на русском языке, кратко и структурированно. '
            . 'Если в контексте есть результаты веб-поиска — опирайся на них и указывай источники. '
            . 'Не выдумывай персональные данные студентов.';

        $rows = $DB->get_records('local_kaznu_message', ['conversationid' => $conversationid], 'timecreated ASC');
        $rows = array_slice($rows, -$max);

        $messages = [['role' => 'system', 'content' => $system]];
        foreach ($rows as $r) {
            if ($r->role === 'tool') {
                $messages[] = [
                    'role' => 'system',
                    'content' => "Результаты поиска в интернете:\n" . $r->content,
                ];
                continue;
            }
            if (in_array($r->role, ['user', 'assistant'], true)) {
                $messages[] = ['role' => $r->role, 'content' => $r->content];
            }
        }
        return $messages;
    }

    private static function call_openai(string $apikey, string $model, array $messages): string {
        $payload = json_encode([
            'model' => $model,
            'messages' => $messages,
            'temperature' => 0.6,
        ], JSON_UNESCAPED_UNICODE);

        $curl = new \curl();
        $curl->setHeader([
            'Content-Type: application/json',
            'Authorization: Bearer ' . $apikey,
        ]);
        $response = $curl->post('https://api.openai.com/v1/chat/completions', $payload);
        $code = $curl->get_info()['http_code'] ?? 0;

        if ($code < 200 || $code >= 300 || !$response) {
            debugging('OpenAI error HTTP ' . $code . ': ' . $response, DEBUG_DEVELOPER);
            throw new \moodle_exception('error_generic', 'local_kaznu');
        }

        $data = json_decode($response, true);
        $text = $data['choices'][0]['message']['content'] ?? '';
        if ($text === '') {
            throw new \moodle_exception('error_generic', 'local_kaznu');
        }
        return $text;
    }
}
