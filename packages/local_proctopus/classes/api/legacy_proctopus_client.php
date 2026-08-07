<?php
namespace local_proctopus\api;

use local_proctopus\http\proctopus_transport_interface;
use local_proctopus\http\moodle_curl_transport;
use local_proctopus\http\stub_transport;

defined('MOODLE_INTERNAL') || die();

/**
 * Legacy Proctopus start adapter (KazNU-era contract).
 *
 * GET {base}{start_path}?token=<JWT>
 * Not a universal API — only used when proctopus_legacy_mode=1.
 * Live network only when proctopus_live_calls_enabled=1.
 */
class legacy_proctopus_client {
    public function __construct(
        private readonly ?proctopus_transport_interface $transport = null
    ) {
    }

    private function transport(): proctopus_transport_interface {
        if ($this->transport !== null) {
            return $this->transport;
        }
        if (local_proctopus_live_calls_enabled()) {
            return new moodle_curl_transport();
        }
        return new stub_transport();
    }

    /**
     * Call legacy start endpoint.
     *
     * @return array{ok:bool, redirect_url?:string, http?:int, duration_ms?:int, error?:string, body_safe?:string}
     */
    public function start(string $jwt, string $correlation): array {
        if (!local_proctopus_legacy_mode()) {
            return ['ok' => false, 'error' => 'legacy_mode_off'];
        }
        if ($jwt === '') {
            return ['ok' => false, 'error' => 'missing_token'];
        }

        $base = local_proctopus_baseurl();
        $path = local_proctopus_start_path();
        if (!str_starts_with($base, 'https://')) {
            return ['ok' => false, 'error' => 'https_required'];
        }
        $host = parse_url($base, PHP_URL_HOST);
        if (!$host || !in_array(strtolower($host), local_proctopus_allowed_hosts(), true)) {
            // Still allow offline stub when live calls disabled (stub never hits host).
            if (local_proctopus_live_calls_enabled()) {
                return ['ok' => false, 'error' => 'host_not_allowlisted'];
            }
        }

        // Build path only; token never logged.
        $url = rtrim($base, '/') . $path;
        $urlwithtoken = $url . (str_contains($url, '?') ? '&' : '?') . 'token=' . rawurlencode($jwt);

        $t0 = microtime(true);
        $response = $this->transport()->request('GET', $urlwithtoken, [
            'Accept' => 'application/json, text/plain, */*',
            'X-Correlation-Id' => $correlation,
        ]);
        $ms = (int) round((microtime(true) - $t0) * 1000);
        $status = (int) ($response['status'] ?? 0);
        $body = (string) ($response['body'] ?? '');

        local_proctopus_safe_log($correlation, 'legacy_start_response', $status, $ms);

        if (!empty($response['redirect_url'])) {
            $redir = (string) $response['redirect_url'];
            if (!local_proctopus_is_redirect_host_allowed($redir)) {
                return ['ok' => false, 'error' => 'redirect_host_forbidden', 'http' => $status, 'duration_ms' => $ms];
            }
            return ['ok' => true, 'redirect_url' => $redir, 'http' => $status, 'duration_ms' => $ms];
        }

        if ($status >= 400 || $status === 0) {
            return ['ok' => false, 'error' => 'http_error', 'http' => $status, 'duration_ms' => $ms];
        }

        $trim = ltrim($body);
        if ($trim !== '' && ($trim[0] === '{' || $trim[0] === '[')) {
            $json = json_decode($body, true);
            if (!is_array($json)) {
                return ['ok' => false, 'error' => 'invalid_json', 'http' => $status, 'duration_ms' => $ms];
            }
            $redir = (string) ($json['url'] ?? $json['redirectUrl'] ?? $json['redirect_url'] ?? '');
            if ($redir === '') {
                // Some legacy APIs only return OK and browser uses same host UI — not inventing.
                return ['ok' => false, 'error' => 'missing_redirect_in_json', 'http' => $status, 'duration_ms' => $ms];
            }
            if (!local_proctopus_is_redirect_host_allowed($redir)) {
                return ['ok' => false, 'error' => 'redirect_host_forbidden', 'http' => $status, 'duration_ms' => $ms];
            }
            return ['ok' => true, 'redirect_url' => $redir, 'http' => $status, 'duration_ms' => $ms];
        }

        // HTML / plain: do not render inside Moodle. For live legacy flow, if the
        // start URL itself is the entry point, hand browser to HTTPS allowlisted start
        // (token only travels to allowlisted Proctopus host, never stored/logged).
        if (local_proctopus_live_calls_enabled() && $status >= 200 && $status < 400) {
            $entry = rtrim(local_proctopus_baseurl(), '/') . local_proctopus_start_path();
            $entry = $entry . (str_contains($entry, '?') ? '&' : '?') . 'token=' . rawurlencode($jwt);
            if (local_proctopus_is_redirect_host_allowed($entry)) {
                local_proctopus_safe_log($correlation, 'legacy_start_browser_entry', $status, $ms);
                return [
                    'ok' => true,
                    'redirect_url' => $entry,
                    'http' => $status,
                    'duration_ms' => $ms,
                    'response_type' => 'browser_entry',
                ];
            }
        }

        return ['ok' => false, 'error' => 'unsupported_response', 'http' => $status, 'duration_ms' => $ms];
    }
}
