<?php
namespace local_kaznu\api;

use local_kaznu\api\dto\login_result;
use local_kaznu\api\dto\schedule_item;
use local_kaznu\api\dto\semester_info;
use local_kaznu\api\dto\student_profile;
use local_kaznu\api\dto\subject;
use local_kaznu\api\dto\exam_item;
use local_kaznu\api\dto\midterm_item;
use local_kaznu\api\dto\assessment_item;
use local_kaznu\api\dto\attendance_item;

defined('MOODLE_INTERNAL') || die();

/**
 * HTTPS client for https://univerapi.kaznu.kz (read-only student endpoints).
 *
 * Security:
 * - Password only used to build the login request; never stored or logged.
 * - Logged paths never include query strings.
 * - Cookie jar is request-scoped on this instance (not persisted by the client).
 * - IIN is stripped when mapping GetStudentInfo.
 */
class univer_client implements univer_client_interface {
    private cookie_jar $jar;
    private string $baseurl;
    private string $defaultculture;
    private http_transport_interface $transport;
    private ?string $lastlogin = null;

    public function __construct(
        ?string $baseurl = null,
        ?int $timeout = null,
        ?http_transport_interface $transport = null,
        ?string $culture = null
    ) {
        $configured = (string) (get_config('local_kaznu', 'univer_baseurl') ?: 'https://univerapi.kaznu.kz');
        $baseurl = $baseurl ?? $configured;
        $baseurl = rtrim($baseurl, '/');
        if (!preg_match('#^https://#i', $baseurl)) {
            throw new univer_exception('univer_https_required', 'Base URL must be HTTPS');
        }
        $this->baseurl = $baseurl;
        $this->defaultculture = $culture
            ?? (string) (get_config('local_kaznu', 'univer_culture') ?: 'ru-RU');
        $timeout = $timeout ?? (int) (get_config('local_kaznu', 'univer_timeout') ?: 20);
        $this->transport = $transport ?? new curl_transport(max(5, $timeout));
        $this->jar = new cookie_jar();
    }

    public function get_cookie_jar(): cookie_jar {
        return $this->jar;
    }

    public function with_cookie_jar(cookie_jar $jar): self {
        $clone = clone $this;
        $clone->jar = $jar;
        return $clone;
    }

    public function login(string $login, string $password): login_result {
        $login = trim($login);
        if ($login === '' || $password === '') {
            throw new univer_exception('univer_login_empty');
        }

        // Password is only placed in the remote query; never logged.
        $url = $this->baseurl . '/user/login?login=' . rawurlencode($login)
            . '&password=' . rawurlencode($password);

        $response = $this->raw_request('GET', $url, false);
        $payload = $this->decode_uni_response($response, '/user/login');

        $code = (int) ($payload['code'] ?? 2);
        if ($code !== 0) {
            $map = [
                1 => 'univer_already_logged_in',
                2 => 'univer_bad_credentials',
                3 => 'univer_login_empty',
            ];
            throw new univer_exception($map[$code] ?? 'univer_auth_failed', '', $code);
        }

        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $userid = (int) ($payload['userId'] ?? ($data['id'] ?? 0));
        $usertype = (string) ($data['userType'] ?? 'STUDENT');
        if ($userid <= 0) {
            throw new univer_exception('univer_auth_failed', 'Missing userId');
        }
        if (strtoupper($usertype) !== 'STUDENT') {
            throw new univer_exception('univer_not_student', '', $code, $usertype);
        }

        $this->lastlogin = (string) ($data['login'] ?? $login);

        return new login_result(
            $userid,
            $this->lastlogin,
            $usertype,
            (int) ($data['access'] ?? 0),
            isset($payload['sessionId']) ? (string) $payload['sessionId'] : null,
            (string) ($payload['message'] ?? '')
        );
    }

    public function logout(): void {
        try {
            $this->request_json('GET', '/user/logout');
        } catch (univer_exception $e) {
            // Best-effort logout.
            debugging('Univer logout: ' . $e->errorcode, DEBUG_DEVELOPER);
        }
        $this->jar->clear();
        $this->lastlogin = null;
    }

    public function get_semester_info(): semester_info {
        $payload = $this->request_json('GET', '/student/semesterInfo');
        $data = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        return new semester_info(
            (int) ($data['currentYear'] ?? 0),
            (int) ($data['currentSemester'] ?? 0),
            (int) ($data['semestersCount'] ?? 0),
            (string) ($data['currentRk'] ?? '')
        );
    }

    public function get_profile(string $culture = 'ru-RU'): student_profile {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $info = $this->request_json('GET', '/student/GetStudentInfo?culture=' . rawurlencode($culture));
        $data = is_array($info['data'] ?? null) ? $info['data'] : [];

        // Explicitly drop IIN / sensitive keys before mapping.
        unset($data['iin'], $data['IIN'], $data['Iin']);

        $pairs = [];
        try {
            $profilepayload = $this->request_json('GET', '/student/profile/' . rawurlencode($culture));
            if (is_array($profilepayload['data'] ?? null)) {
                $pairs = $profilepayload['data'];
            }
        } catch (univer_exception $e) {
            debugging('Univer profile fallback: ' . $e->errorcode, DEBUG_DEVELOPER);
        }

        $faculty = $this->extract_profile_pair($pairs, 'faculty');
        $speciality = $this->as_string($data['specialityOkpd'] ?? null);
        $group = $this->extract_profile_pair($pairs, 'group');
        if ($group === '') {
            $group = $this->extract_profile_pair($pairs, 'edu_group');
        }

        $email = null;
        $emailtech = true;
        if (!empty($data['contacts']) && is_array($data['contacts'])) {
            foreach ($data['contacts'] as $c) {
                if (!is_array($c)) {
                    continue;
                }
                $val = trim($this->as_string($c['contactValue'] ?? null));
                if ($val !== '' && str_contains($val, '@')) {
                    $email = $val;
                    $emailtech = false;
                    break;
                }
            }
        }

        $univeruserid = (int) ($info['userId'] ?? 0);
        if ($univeruserid <= 0 && $this->lastlogin) {
            // Prefer userId from envelope; login_result id used by caller if needed.
            $univeruserid = 0;
        }
        if ($email === null) {
            // Caller should replace 0 with real id from login_result.
            $email = 'univer_' . max(0, $univeruserid) . '@users.invalid';
            $emailtech = true;
        }

        // nameEn/snameEn are often null in Univer JSON; coerce to '' (strict string props).
        $firstnameen = $this->as_string($data['nameEn'] ?? ($data['NameEn'] ?? null));
        $lastnameen = $this->as_string($data['snameEn'] ?? ($data['SnameEn'] ?? null));
        $firstname = $this->as_string($data['name'] ?? null);
        if ($firstname === '') {
            $firstname = $firstnameen;
        }
        $lastname = $this->as_string($data['sname'] ?? null);
        if ($lastname === '') {
            $lastname = $lastnameen;
        }

        return new student_profile(
            $univeruserid,
            $this->as_string($this->lastlogin),
            $firstname,
            $lastname,
            $this->as_string($data['fname'] ?? null),
            $email,
            $emailtech,
            $faculty,
            $speciality,
            (int) ($data['course'] ?? 0),
            $group,
            $firstnameen,
            $lastnameen
        );
    }

    public function get_group_list(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $path = '/student/groupList/' . $year . '/' . $semester . '/' . rawurlencode($culture);
        $payload = $this->request_json('GET', $path);
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $subjectid = (int) ($row['subjectId'] ?? 0);
            if ($subjectid <= 0) {
                continue;
            }
            $out[] = new subject(
                $subjectid,
                (string) ($row['subjectName'] ?? ''),
                (int) ($row['groupId'] ?? 0),
                (int) ($row['planPosId'] ?? 0),
                (int) ($row['academYear'] ?? $year),
                (int) ($row['academSemester'] ?? $semester),
                (int) ($row['educTypeId'] ?? 0),
                (string) ($row['educTypeName'] ?? ''),
                (int) ($row['teacherId'] ?? 0),
                (int) ($row['langId'] ?? 0)
            );
        }
        return $out;
    }

    public function get_schedule(int $year = 0, int $semester = 0, int $day = 1, string $culture = 'ru-RU'): array {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $day = max(1, min(7, $day));
        $path = '/student/schedule/' . $year . '/' . $semester . '/' . $day . '/' . rawurlencode($culture);
        $payload = $this->request_json('GET', $path);
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $subject = trim((string) ($row['subject'] ?? ''));
            if ($subject === '') {
                continue;
            }
            $out[] = new schedule_item(
                $day,
                (int) ($row['type'] ?? 0),
                $subject,
                (string) ($row['eductype'] ?? ''),
                (string) ($row['teacher'] ?? ''),
                (string) ($row['audienceFaculty'] ?? ''),
                (string) ($row['audience'] ?? ''),
                (string) ($row['timeBegin'] ?? ''),
                (string) ($row['timeEnd'] ?? ''),
                (string) ($row['from'] ?? ''),
                (string) ($row['to'] ?? ''),
                (int) ($row['denominator'] ?? 0)
            );
        }
        return $out;
    }

    /**
     * All weekdays (1..7) for the period.
     *
     * @return schedule_item[]
     */
    public function get_schedule_week(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        $out = [];
        for ($day = 1; $day <= 7; $day++) {
            foreach ($this->get_schedule($year, $semester, $day, $culture) as $item) {
                $out[] = $item;
            }
        }
        return $out;
    }

    /**
     * @return exam_item[]
     */
    public function get_exam_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $path = '/student/examschedule/' . $year . '/' . $semester . '/' . rawurlencode($culture);
        $payload = $this->request_json('GET', $path);
        return $this->map_exam_like($payload['data'] ?? null, false);
    }

    /**
     * @return midterm_item[]
     */
    public function get_midterm_schedule(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $path = '/student/midtermschedule/' . $year . '/' . $semester . '/' . rawurlencode($culture);
        $payload = $this->request_json('GET', $path);
        return $this->map_exam_like($payload['data'] ?? null, true);
    }

    /**
     * @return assessment_item[]
     */
    public function get_attestation(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $path = '/student/attestation/' . $year . '/' . $semester . '/' . rawurlencode($culture);
        $payload = $this->request_json('GET', $path);
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            // Meta envelopes like {course, semestersCount} are not grade rows.
            if (!isset($row['subjectName'])) {
                continue;
            }
            $name = trim((string) $row['subjectName']);
            if ($name === '') {
                continue;
            }
            $out[] = new assessment_item(
                $name,
                (string) ($row['credit'] ?? ''),
                (string) ($row['resultRK1'] ?? ''),
                (string) ($row['resultRK2'] ?? ''),
                (string) ($row['result'] ?? ''),
                (string) ($row['totalRes'] ?? ''),
                (string) ($row['GPA'] ?? ($row['gpa'] ?? '')),
                (string) ($row['symbol'] ?? ''),
                (string) ($row['text'] ?? '')
            );
        }
        return $out;
    }

    /**
     * @return attendance_item[]
     */
    public function get_attendance(int $year = 0, int $semester = 0, string $culture = 'ru-RU'): array {
        $culture = $culture !== '' ? $culture : $this->defaultculture;
        $path = '/student/attendance/' . $year . '/' . $semester . '/' . rawurlencode($culture);
        $payload = $this->request_json('GET', $path);
        $rows = is_array($payload['data'] ?? null) ? $payload['data'] : [];
        $out = [];
        foreach ($rows as $row) {
            // Nested triple: [meta, {sum}, {sum}].
            if (!is_array($row)) {
                continue;
            }
            $meta = null;
            $sum1 = '';
            $sum2 = '';
            if (isset($row[0]) && is_array($row[0])) {
                $meta = $row[0];
                $sum1 = isset($row[1]['sum']) ? (string) $row[1]['sum'] : '';
                $sum2 = isset($row[2]['sum']) ? (string) $row[2]['sum'] : '';
            } else if (isset($row['name_1'])) {
                $meta = $row;
            } else {
                continue;
            }
            $subject = trim((string) ($meta['name_1'] ?? ''));
            if ($subject === '') {
                continue;
            }
            $out[] = new attendance_item(
                $subject,
                (string) ($meta['name_2'] ?? ''),
                (string) ($meta['name_3'] ?? ''),
                (string) ($meta['groupInfo'] ?? ''),
                $sum1,
                $sum2
            );
        }
        return $out;
    }

    /**
     * @param mixed $data
     * @return exam_item[]|midterm_item[]
     */
    private function map_exam_like($data, bool $midterm): array {
        $rows = is_array($data) ? $data : [];
        $out = [];
        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }
            $subject = trim((string) ($row['subject'] ?? ''));
            if ($subject === '') {
                continue;
            }
            $args = [
                $subject,
                (string) ($row['type'] ?? ''),
                (string) ($row['teacher'] ?? ''),
                (string) ($row['audienceFaculty'] ?? ''),
                (string) ($row['audience'] ?? ''),
                (string) ($row['date'] ?? ''),
                (string) ($row['time'] ?? ''),
            ];
            $out[] = $midterm ? new midterm_item(...$args) : new exam_item(...$args);
        }
        return $out;
    }

    /**
     * Build a profile DTO with a known Univer user id (after login).
     */
    public function get_profile_for_user(int $univeruserid, string $culture = 'ru-RU'): student_profile {
        $profile = $this->get_profile($culture);
        $email = $profile->email;
        $emailtech = $profile->emailistechnical;
        if ($emailtech || $email === null || str_ends_with((string) $email, '@users.invalid')) {
            $email = 'univer_' . $univeruserid . '@users.invalid';
            $emailtech = true;
        }
        return new student_profile(
            $univeruserid,
            $profile->login !== '' ? $profile->login : (string) $this->lastlogin,
            $profile->firstname,
            $profile->lastname,
            $profile->firstnamephonetic,
            $email,
            $emailtech,
            $profile->faculty,
            $profile->speciality,
            $profile->coursenumber,
            $profile->groupname,
            $profile->firstnameen,
            $profile->lastnameen
        );
    }

    /**
     * @return array<string,mixed>
     */
    private function request_json(string $method, string $path): array {
        $url = $this->baseurl . $path;
        $response = $this->raw_request($method, $url, true);
        return $this->decode_uni_response($response, $this->safe_path($path));
    }

    private function raw_request(string $method, string $url, bool $sendcookies): http_response {
        $headers = ['Accept' => 'application/json'];
        if ($sendcookies && !$this->jar->is_empty()) {
            $headers['Cookie'] = $this->jar->to_header();
        }

        try {
            $response = $this->transport->request($method, $url, $headers);
        } catch (\Throwable $e) {
            throw new univer_exception('univer_http_error', $this->safe_path($url));
        }

        $setcookies = $response->get_header_values('set-cookie');
        if ($setcookies) {
            $this->jar->merge_from_set_cookie($setcookies);
        }

        if ($response->status >= 500) {
            throw new univer_exception('univer_server_error', 'HTTP ' . $response->status . ' ' . $this->safe_path($url));
        }
        if ($response->status === 401 || $response->status === 403) {
            throw new univer_exception('univer_auth_failed', 'HTTP ' . $response->status);
        }
        if ($response->status === 404) {
            throw new univer_exception('univer_not_found', $this->safe_path($url));
        }

        return $response;
    }

    /**
     * @return array<string,mixed>
     */
    private function decode_uni_response(http_response $response, string $safepath): array {
        $decoded = json_decode($response->body, true);
        if (!is_array($decoded)) {
            throw new univer_exception('univer_bad_response', $safepath);
        }
        if (isset($decoded['code']) && (int) $decoded['code'] === 3) {
            throw new univer_exception('univer_auth_failed', $safepath, 3);
        }
        return $decoded;
    }

    /**
     * Strip query string so passwords never appear in logs/exceptions.
     */
    private function safe_path(string $urlorpath): string {
        $path = $urlorpath;
        if (str_contains($path, '://')) {
            $parts = parse_url($path);
            $path = ($parts['path'] ?? '/') . (isset($parts['query']) ? '' : '');
            // Intentionally drop query.
            $path = $parts['path'] ?? '/';
        } else {
            $qpos = strpos($path, '?');
            if ($qpos !== false) {
                $path = substr($path, 0, $qpos);
            }
        }
        return $path;
    }

    /**
     * @param array<int,mixed> $pairs
     */
    private function extract_profile_pair(array $pairs, string $key): string {
        foreach ($pairs as $item) {
            if (!is_array($item)) {
                continue;
            }
            // Shape: [ {"faculty":"Факультет: …"} ] or nested.
            foreach ($item as $k => $v) {
                if (is_array($v)) {
                    foreach ($v as $kk => $vv) {
                        if ((string) $kk === $key) {
                            return $this->strip_profile_label((string) $vv);
                        }
                    }
                } else if ((string) $k === $key) {
                    return $this->strip_profile_label((string) $v);
                }
            }
        }
        return '';
    }

    private function strip_profile_label(string $value): string {
        $value = trim($value);
        if (str_contains($value, ':')) {
            $parts = explode(':', $value, 2);
            return trim($parts[1]);
        }
        return $value;
    }

    /**
     * Coerce Univer JSON scalars to string; null/array/object → ''.
     */
    private function as_string(mixed $value): string {
        if ($value === null || is_array($value) || is_object($value)) {
            return '';
        }
        return trim((string) $value);
    }
}
