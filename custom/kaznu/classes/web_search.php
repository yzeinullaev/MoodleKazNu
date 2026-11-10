<?php
namespace local_kaznu;

defined('MOODLE_INTERNAL') || die();

/**
 * Web search for AI (Tavily preferred, DuckDuckGo HTML fallback).
 */
class web_search {

    public static function search(string $query, int $maxresults = 5): string {
        $query = trim($query);
        if ($query === '') {
            return '';
        }
        $tavily = get_config('local_kaznu', 'tavily_apikey');
        if (!empty($tavily)) {
            $out = self::tavily_search($query, $tavily, $maxresults);
            if ($out !== '') {
                return $out;
            }
        }
        return self::duckduckgo_fallback($query, $maxresults);
    }

    private static function tavily_search(string $query, string $apikey, int $maxresults): string {
        $payload = json_encode([
            'api_key' => $apikey,
            'query' => $query,
            'search_depth' => 'basic',
            'max_results' => $maxresults,
            'include_answer' => true,
        ]);
        $curl = new \curl();
        $curl->setHeader(['Content-Type: application/json']);
        $response = $curl->post('https://api.tavily.com/search', $payload);
        if (!$response) {
            return '';
        }
        $data = json_decode($response, true);
        if (!is_array($data)) {
            return '';
        }
        $parts = [];
        if (!empty($data['answer'])) {
            $parts[] = 'Краткий ответ: ' . $data['answer'];
        }
        if (!empty($data['results']) && is_array($data['results'])) {
            foreach ($data['results'] as $i => $row) {
                if ($i >= $maxresults) {
                    break;
                }
                $title = $row['title'] ?? '';
                $url = $row['url'] ?? '';
                $content = $row['content'] ?? '';
                $parts[] = ($i + 1) . ". {$title}\n{$url}\n{$content}";
            }
        }
        return implode("\n\n", $parts);
    }

  /** @return string Plain-text snippets from DuckDuckGo lite (best-effort). */
    private static function duckduckgo_fallback(string $query, int $maxresults): string {
        $curl = new \curl();
        $html = $curl->post('https://html.duckduckgo.com/html/', [
            'q' => $query,
        ]);
        if (!$html) {
            return 'Поиск в интернете недоступен. Настройте Tavily API key в админке.';
        }
        $parts = [];
        if (preg_match_all(
            '/<a[^>]+class="result__a"[^>]*href="([^"]*)"[^>]*>(.*?)<\/a>.*?<a[^>]+class="result__snippet"[^>]*>(.*?)<\/a>/s',
            $html,
            $matches,
            PREG_SET_ORDER
        )) {
            foreach ($matches as $i => $m) {
                if ($i >= $maxresults) {
                    break;
                }
                $title = html_entity_decode(strip_tags($m[2]));
                $url = html_entity_decode($m[1]);
                $snippet = html_entity_decode(strip_tags($m[3]));
                $parts[] = ($i + 1) . ". {$title}\n{$url}\n{$snippet}";
            }
        }
        return $parts ? implode("\n\n", $parts) : 'По запросу ничего не найдено.';
    }
}
