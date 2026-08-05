<?php
namespace local_kaznu\api;

defined('MOODLE_INTERNAL') || die();

/**
 * In-memory HTTP cookie jar for a single Univer client instance (request-scoped).
 */
class cookie_jar {
    /** @var array<string,string> */
    private array $cookies = [];

    /**
     * @param array<string,string> $cookies
     */
    public function __construct(array $cookies = []) {
        foreach ($cookies as $name => $value) {
            $this->set((string) $name, (string) $value);
        }
    }

    public function set(string $name, string $value): void {
        $name = trim($name);
        if ($name === '') {
            return;
        }
        $this->cookies[$name] = $value;
    }

    public function get(string $name): ?string {
        return $this->cookies[$name] ?? null;
    }

    public function has(string $name): bool {
        return array_key_exists($name, $this->cookies);
    }

    public function clear(): void {
        $this->cookies = [];
    }

    /**
     * @return array<string,string>
     */
    public function all(): array {
        return $this->cookies;
    }

    public function is_empty(): bool {
        return $this->cookies === [];
    }

    /**
     * Merge Set-Cookie header values (name=value; Path=...).
     *
     * @param string[] $setcookieheaders
     */
    public function merge_from_set_cookie(array $setcookieheaders): void {
        foreach ($setcookieheaders as $header) {
            $header = trim((string) $header);
            if ($header === '') {
                continue;
            }
            $parts = explode(';', $header, 2);
            $nv = explode('=', $parts[0], 2);
            if (count($nv) < 2) {
                continue;
            }
            $name = trim($nv[0]);
            $value = trim($nv[1]);
            if ($name !== '') {
                $this->set($name, $value);
            }
        }
    }

    public function to_header(): string {
        if ($this->cookies === []) {
            return '';
        }
        $pairs = [];
        foreach ($this->cookies as $name => $value) {
            $pairs[] = $name . '=' . $value;
        }
        return implode('; ', $pairs);
    }

    /**
     * Export for short-lived encrypted vault (no logging).
     *
     * @return array<string,string>
     */
    public function export(): array {
        return $this->cookies;
    }

    /**
     * @param array<string,string> $data
     */
    public static function from_export(array $data): self {
        return new self($data);
    }
}
