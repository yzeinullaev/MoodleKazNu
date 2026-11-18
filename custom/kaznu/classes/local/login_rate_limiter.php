<?php
namespace local_kaznu\local;

defined('MOODLE_INTERNAL') || die();

/**
 * IP + normalized-login rate limiting via Moodle Cache API.
 *
 * NAT-aware: IP threshold is higher than per-login threshold.
 * Stores only hashed keys — never passwords or raw full profiles.
 */
final class login_rate_limiter {
    public const CACHE_AREA = 'login_ratelimit';

    private int $maxperlogin;
    private int $maxperip;
    private int $window;
    private int $lockout;

    public function __construct(
        ?int $maxperlogin = null,
        ?int $maxperip = null,
        ?int $window = null,
        ?int $lockout = null
    ) {
        $this->maxperlogin = $maxperlogin ?? max(3, (int) (get_config('local_kaznu', 'univer_rl_login') ?: 5));
        $this->maxperip = $maxperip ?? max(5, (int) (get_config('local_kaznu', 'univer_rl_ip') ?: 100));
        $this->window = $window ?? max(60, (int) (get_config('local_kaznu', 'univer_rl_window') ?: 900));
        $this->lockout = $lockout ?? max(60, (int) (get_config('local_kaznu', 'univer_rl_lockout') ?: 900));
    }

    public function is_blocked(string $login, string $ip): bool {
        $now = time();
        foreach ([$this->login_key($login), $this->ip_key($ip)] as $key) {
            $row = $this->cache()->get($key);
            if (!is_array($row)) {
                continue;
            }
            if (!empty($row['locked_until']) && (int) $row['locked_until'] > $now) {
                return true;
            }
        }
        return false;
    }

    public function register_failure(string $login, string $ip): void {
        $this->bump($this->login_key($login), $this->maxperlogin);
        $this->bump($this->ip_key($ip), $this->maxperip);
    }

    public function register_success(string $login, string $ip): void {
        $this->cache()->delete($this->login_key($login));
        // Soft-reset IP counter (keep partial history for shared NAT).
        $ipkey = $this->ip_key($ip);
        $row = $this->cache()->get($ipkey);
        if (is_array($row)) {
            $row['count'] = max(0, (int) ($row['count'] ?? 0) - 1);
            $row['locked_until'] = 0;
            $this->cache()->set($ipkey, $row);
        }
    }

    private function bump(string $key, int $max): void {
        $now = time();
        $row = $this->cache()->get($key);
        if (!is_array($row) || ((int) ($row['started'] ?? 0) + $this->window) < $now) {
            $row = ['count' => 0, 'started' => $now, 'locked_until' => 0];
        }
        $row['count'] = (int) $row['count'] + 1;
        if ($row['count'] >= $max) {
            $row['locked_until'] = $now + $this->lockout;
        }
        $this->cache()->set($key, $row);
    }

    private function login_key(string $login): string {
        $norm = \core_text::strtolower(trim($login));
        return 'l:' . hash('sha256', $norm);
    }

    private function ip_key(string $ip): string {
        // Truncate IPv6 / keep network hint without logging full address in event data.
        $ip = trim($ip);
        if ($ip === '') {
            $ip = 'unknown';
        }
        return 'i:' . hash('sha256', $ip);
    }

    private function cache(): \cache {
        return \cache::make('local_kaznu', self::CACHE_AREA);
    }
}
