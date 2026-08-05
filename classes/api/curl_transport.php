<?php
namespace local_kaznu\api;

defined('MOODLE_INTERNAL') || die();

/**
 * Moodle curl-based HTTPS transport.
 */
class curl_transport implements http_transport_interface {
    public function __construct(
        private readonly int $timeout = 20
    ) {
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): http_response {
        global $CFG;
        require_once($CFG->libdir . '/filelib.php');

        $curl = new \curl(['timeout' => $this->timeout, 'connecttimeout' => min(10, $this->timeout)]);
        $curl->setopt([
            'CURLOPT_HEADER' => true,
            'CURLOPT_RETURNTRANSFER' => true,
            'CURLOPT_FOLLOWLOCATION' => false,
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
        ]);

        $headerlines = [];
        foreach ($headers as $name => $value) {
            $headerlines[] = $name . ': ' . $value;
        }

        $method = strtoupper($method);
        $raw = '';
        if ($method === 'GET') {
            $raw = (string) $curl->get($url, [], ['CURLOPT_HTTPHEADER' => $headerlines]);
        } elseif ($method === 'POST') {
            $raw = (string) $curl->post($url, $body ?? '', ['CURLOPT_HTTPHEADER' => $headerlines]);
        } else {
            throw new univer_exception('univer_http_method', 'Unsupported HTTP method');
        }

        $info = $curl->get_info();
        $status = isset($info['http_code']) ? (int) $info['http_code'] : 0;
        $headersize = isset($info['header_size']) ? (int) $info['header_size'] : 0;

        $rawheaders = $headersize > 0 ? substr($raw, 0, $headersize) : '';
        $responsebody = $headersize > 0 ? substr($raw, $headersize) : $raw;

        return new http_response($status, (string) $responsebody, $this->parse_headers($rawheaders));
    }

    /**
     * @return array<string,string|string[]>
     */
    private function parse_headers(string $rawheaders): array {
        $out = [];
        foreach (preg_split("/\r\n|\n|\r/", $rawheaders) as $line) {
            if ($line === '' || !str_contains($line, ':')) {
                continue;
            }
            [$name, $value] = explode(':', $line, 2);
            $name = trim($name);
            $value = trim($value);
            $lname = strtolower($name);
            if ($lname === 'set-cookie') {
                if (!isset($out['set-cookie'])) {
                    $out['set-cookie'] = [];
                }
                $out['set-cookie'][] = $value;
            } else {
                $out[$name] = $value;
            }
        }
        return $out;
    }
}
