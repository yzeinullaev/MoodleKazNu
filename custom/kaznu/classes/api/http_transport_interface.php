<?php
namespace local_kaznu\api;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP transport abstraction (mockable in PHPUnit).
 */
interface http_transport_interface {
    /**
     * @param array<string,string> $headers
     */
    public function request(string $method, string $url, array $headers = [], ?string $body = null): http_response;
}
