<?php
namespace local_proctopus\http;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle curl transport for live legacy calls (only when live flag on).
 * Never enables CURLOPT_VERBOSE. Never logs full URL with token.
 */
class moodle_curl_transport implements proctopus_transport_interface {
    public function request(string $method, string $url, array $headers = [], string $body = ''): array {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        // Host allowlist gate.
        $host = parse_url($url, PHP_URL_HOST);
        if (!$host || !in_array(strtolower($host), local_proctopus_allowed_hosts(), true)) {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        if (strtolower((string) parse_url($url, PHP_URL_SCHEME)) !== 'https') {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }

        $curl = new \curl();
        $connect = (int) (get_config('local_proctopus', 'proctopus_connect_timeout') ?: 5);
        $total = (int) (get_config('local_proctopus', 'proctopus_timeout') ?: 20);
        $options = [
            'CURLOPT_CONNECTTIMEOUT' => $connect,
            'CURLOPT_TIMEOUT' => $total,
            'CURLOPT_RETURNTRANSFER' => true,
            // Capture headers to read Location without auto-follow chains.
            'CURLOPT_HEADER' => true,
            'CURLOPT_VERBOSE' => false,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_MAXREDIRS' => 0,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ];
        $hdr = [];
        foreach ($headers as $k => $v) {
            $hdr[] = $k . ': ' . $v;
        }
        if ($hdr) {
            $options['CURLOPT_HTTPHEADER'] = $hdr;
        }

        $method = strtoupper($method);
        if ($method === 'GET') {
            $raw = $curl->get($url, $options);
        } else if ($method === 'POST') {
            $raw = $curl->post($url, $body, $options);
        } else {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }

        $info = $curl->get_info();
        $status = (int) ($info['http_code'] ?? 0);
        $rawstr = is_string($raw) ? $raw : '';
        $headerstr = '';
        $bodystr = $rawstr;
        if ($rawstr !== '' && str_contains($rawstr, "\r\n\r\n")) {
            [$headerstr, $bodystr] = explode("\r\n\r\n", $rawstr, 2);
        } else if ($rawstr !== '' && str_contains($rawstr, "\n\n")) {
            [$headerstr, $bodystr] = explode("\n\n", $rawstr, 2);
        }

        // Redirect without following; Location may be relative.
        $redirect = '';
        if (in_array($status, [301, 302, 303, 307, 308], true)) {
            if (preg_match('/^Location:\s*(.+)$/mi', $headerstr, $m)) {
                $redirect = trim($m[1]);
            } else if (!empty($info['redirect_url'])) {
                $redirect = (string) $info['redirect_url'];
            }
            if ($redirect !== '' && str_starts_with($redirect, '/')) {
                $basehost = parse_url($url, PHP_URL_SCHEME) . '://' . parse_url($url, PHP_URL_HOST);
                $redirect = $basehost . $redirect;
            }
        }

        // Cap body kept in memory (never log it).
        if (strlen($bodystr) > 65536) {
            $bodystr = substr($bodystr, 0, 65536);
        }

        return [
            'status' => $status,
            'body' => $bodystr,
            'headers' => [],
            'redirect_url' => $redirect,
        ];
    }
}
