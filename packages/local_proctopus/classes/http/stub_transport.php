<?php
namespace local_proctopus\http;

defined('MOODLE_INTERNAL') || die();

/**
 * Fully offline stub: records safe call metadata, never opens sockets.
 * When URL contains token=, redacts for stored call log.
 * Returns synthetic redirect to local return endpoint if return_url header provided.
 */
class stub_transport implements proctopus_transport_interface {
    /** @var array<int,array> */
    public array $calls = [];
    public int $status = 200;
    /** Optional force body / redirect */
    public string $body = '{"ok":true,"stub":true}';
    public string $redirecturl = '';

    public function request(string $method, string $url, array $headers = [], string $body = ''): array {
        $safe = preg_replace('/([?&](?:token|jwt|access_token)=)[^&]*/i', '$1[redacted]', $url) ?? $url;
        if (str_contains($safe, 'dl.kaznu.edu.kz') || str_contains($safe, 'kaznu_moodle')) {
            throw new \RuntimeException('Legacy host/account forbidden');
        }
        $this->calls[] = [
            'method' => $method,
            'url' => $safe,
            'headers' => array_keys($headers),
            'body_len' => strlen($body),
            'has_token_query' => str_contains($url, 'token='),
        ];

        $redirect = $this->redirecturl;
        if ($redirect === '' && !empty($headers['X-Stub-Return-Url'])) {
            $redirect = (string) $headers['X-Stub-Return-Url'];
        }
        if ($redirect === '') {
            // Default: tell caller to use signed return on own host (set by session_service).
            $redirect = (string) ($headers['X-Stub-Return-Url'] ?? '');
        }

        return [
            'status' => $this->status,
            'body' => $this->body,
            'headers' => [],
            'redirect_url' => $redirect,
        ];
    }
}
