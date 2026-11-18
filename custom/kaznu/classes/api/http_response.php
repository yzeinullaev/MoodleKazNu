<?php
namespace local_kaznu\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Immutable HTTP response for the Univer transport.
 */
class http_response {
    public function __construct(
        public readonly int $status,
        public readonly string $body,
        /** @var array<string,string|string[]> */
        public readonly array $headers = []
    ) {
    }

    /**
     * @return string[]
     */
    public function get_header_values(string $name): array {
        $name = strtolower($name);
        foreach ($this->headers as $key => $value) {
            if (strtolower((string) $key) !== $name) {
                continue;
            }
            if (is_array($value)) {
                return array_map('strval', $value);
            }
            return [(string) $value];
        }
        return [];
    }
}
