<?php
namespace local_proctopus\http;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTP transport abstraction (stub or Moodle curl).
 */
interface proctopus_transport_interface {
    /**
     * @param array<string,string> $headers
     * @return array{status:int, body:string, headers:array, redirect_url?:string}
     */
    public function request(string $method, string $url, array $headers = [], string $body = ''): array;
}
