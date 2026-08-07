<?php
namespace local_proctopus\jwt;

defined('MOODLE_INTERNAL') || die();

interface jwt_encoder_interface {
    /**
     * @param array<string,mixed> $payload
     * @throws \moodle_exception if algorithm or secret missing
     */
    public function encode(array $payload, string $secret, string $algorithm): string;
}
