<?php
namespace local_proctopus\jwt;

defined('MOODLE_INTERNAL') || die();

/**
 * Minimal HS* JWT encoder with explicit algorithm.
 * Algorithm is configuration-driven (not hard-finalized until provider confirms).
 * Never puts secret into exceptions or logs.
 */
class hs_token_service implements jwt_encoder_interface {
    public function encode(array $payload, string $secret, string $algorithm): string {
        $algorithm = trim($algorithm);
        if ($algorithm === '') {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        if ($secret === '') {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        if (!preg_match('/^HS\d+$/i', $algorithm)) {
            // Scaffold only supports HMAC-family; actual alg confirmed by provider later.
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        $header = ['typ' => 'JWT', 'alg' => strtoupper($algorithm)];
        $segments = [
            $this->b64(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->b64(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signing = $segments[0] . '.' . $segments[1];
        $algo = strtoupper($algorithm);
        $php = match ($algo) {
            'HS256' => 'sha256',
            'HS384' => 'sha384',
            'HS512' => 'sha512',
            default => null,
        };
        if ($php === null) {
            throw new \moodle_exception('proctopus_not_configured', 'local_proctopus');
        }
        $sig = hash_hmac($php, $signing, $secret, true);
        $segments[] = $this->b64($sig);
        return implode('.', $segments);
    }

    /**
     * Build standard time claims + accountName + sessionId. No secret.
     *
     * @param array<string,mixed> $extra
     * @return array<string,mixed>
     */
    public function build_claims(string $sessionid, string $accountname, int $ttl, array $extra = []): array {
        $now = time();
        $claims = [
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + max(30, $ttl),
            'jti' => bin2hex(random_bytes(16)),
            'sessionId' => $sessionid,
            'accountName' => $accountname,
        ];
        return array_merge($claims, $extra);
    }

    private function b64(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}
