<?php
namespace local_proctopus\local;

defined('MOODLE_INTERNAL') || die();

/**
 * Signed one-time return state (userid+cmid+session) with TTL.
 * Not a JWT to Proctopus — internal only.
 */
class state_token {
    /**
     * @param array{sid:int,uid:int,cmid:int,ext:string,exp:int} $data
     */
    public static function issue(array $data): string {
        $payload = self::b64(json_encode($data, JSON_UNESCAPED_SLASHES));
        $sig = hash_hmac('sha256', $payload, local_proctopus_state_key(), true);
        return $payload . '.' . self::b64($sig);
    }

    /**
     * @return array{sid:int,uid:int,cmid:int,ext:string,exp:int}|null
     */
    public static function verify(string $token): ?array {
        $parts = explode('.', $token, 2);
        if (count($parts) !== 2) {
            return null;
        }
        [$payload, $sig] = $parts;
        $expect = self::b64(hash_hmac('sha256', $payload, local_proctopus_state_key(), true));
        if (!hash_equals($expect, $sig)) {
            return null;
        }
        $raw = self::ub64($payload);
        $data = json_decode($raw, true);
        if (!is_array($data)) {
            return null;
        }
        $exp = (int) ($data['exp'] ?? 0);
        $skew = (int) (get_config('local_proctopus', 'proctopus_clock_skew') ?: 60);
        if ($exp + $skew < time()) {
            return null;
        }
        foreach (['sid', 'uid', 'cmid', 'ext'] as $k) {
            if (!isset($data[$k])) {
                return null;
            }
        }
        return [
            'sid' => (int) $data['sid'],
            'uid' => (int) $data['uid'],
            'cmid' => (int) $data['cmid'],
            'ext' => (string) $data['ext'],
            'exp' => $exp,
        ];
    }

    private static function b64(string $d): string {
        return rtrim(strtr(base64_encode($d), '+/', '-_'), '=');
    }

    private static function ub64(string $d): string {
        $remainder = strlen($d) % 4;
        if ($remainder) {
            $d .= str_repeat('=', 4 - $remainder);
        }
        return (string) base64_decode(strtr($d, '-_', '+/'), true);
    }
}
