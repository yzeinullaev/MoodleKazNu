<?php
namespace local_kaznu;

use local_kaznu\api\cookie_jar;
use local_kaznu\api\http_response;
use local_kaznu\api\http_transport_interface;
use local_kaznu\api\univer_client;
use local_kaznu\api\univer_exception;
use local_kaznu\local\course_idnumber;

defined('MOODLE_INTERNAL') || die();

/**
 * Queue-based HTTP stub for unit tests.
 */
class univer_client_test_transport implements http_transport_interface {
    /** @var http_response[] */
    private array $queue = [];
    /** @var array<int,array{method:string,url:string,headers:array}> */
    public array $calls = [];

    public function enqueue(http_response $response): void {
        $this->queue[] = $response;
    }

    public function request(string $method, string $url, array $headers = [], ?string $body = null): http_response {
        $this->calls[] = [
            'method' => $method,
            'url' => $url,
            'headers' => $headers,
        ];
        if ($this->queue === []) {
            throw new \RuntimeException('transport queue empty');
        }
        return array_shift($this->queue);
    }
}

/**
 * @covers \local_kaznu\api\univer_client
 * @covers \local_kaznu\local\course_idnumber
 */
class univer_client_test extends \advanced_testcase {
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
        set_config('univer_baseurl', 'https://univerapi.kaznu.kz', 'local_kaznu');
        set_config('univer_timeout', 10, 'local_kaznu');
        set_config('univer_culture', 'ru-RU', 'local_kaznu');
    }

    public function test_https_required(): void {
        $this->expectException(univer_exception::class);
        new univer_client('http://insecure.example');
    }

    public function test_login_success_sets_cookies(): void {
        $transport = new univer_client_test_transport();
        $transport->enqueue(new http_response(
            200,
            json_encode([
                'code' => 0,
                'message' => 'ok',
                'userId' => 297051,
                'sessionId' => 'abc',
                'data' => [
                    'id' => 297051,
                    'login' => 'oxikbayev.yernur',
                    'userType' => 'STUDENT',
                    'access' => 4194304,
                ],
            ]),
            ['set-cookie' => ['.ASPXAUTH=token123; path=/', 'ASP.NET_SessionId=sess1; path=/']]
        ));

        $client = new univer_client(null, 10, $transport);
        $result = $client->login('oxikbayev.yernur', 'SecretPass#1');

        $this->assertSame(297051, $result->univeruserid);
        $this->assertTrue($result->is_student());
        $this->assertSame('univer_297051@users.invalid', $result->technical_email());
        $this->assertTrue($client->get_cookie_jar()->has('.ASPXAUTH'));
        $this->assertTrue($client->get_cookie_jar()->has('ASP.NET_SessionId'));
        $this->assertCount(1, $transport->calls);
        $this->assertStringContainsString('password=', $transport->calls[0]['url']);
    }

    public function test_login_bad_credentials(): void {
        $transport = new univer_client_test_transport();
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 2,
            'message' => 'bad',
            'userId' => 0,
            'data' => [],
        ])));

        $client = new univer_client(null, 10, $transport);
        try {
            $client->login('someone', 'wrong');
            $this->fail('Expected exception');
        } catch (univer_exception $e) {
            $this->assertSame('univer_bad_credentials', $e->errorcode);
            $this->assertStringNotContainsString('wrong', (string) $e->debuginfo);
            $this->assertStringNotContainsString('password=', $e->getMessage());
        }
    }

    public function test_get_student_info_strips_iin(): void {
        $transport = new univer_client_test_transport();
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'userId' => 10,
            'data' => ['id' => 10, 'login' => 'u1', 'userType' => 'STUDENT', 'access' => 1],
        ]), ['set-cookie' => ['.ASPXAUTH=x']]));
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'userId' => 10,
            'data' => [
                'sname' => 'Oxikbayev',
                'name' => 'Yernur',
                'fname' => '',
                'iin' => '950223301098',
                'facultyId' => '28',
                'specialityOkpd' => '8D06103',
                'course' => 2,
                'snameEn' => 'Oxikbayev',
                'nameEn' => 'Yernur',
            ],
        ])));
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'data' => [
                [['faculty' => 'Факультет: ИТ']],
            ],
        ])));

        $client = new univer_client(null, 10, $transport);
        $client->login('u1', 'p');
        $profile = $client->get_profile_for_user(10);

        $this->assertSame(10, $profile->univeruserid);
        $this->assertSame('Yernur', $profile->firstname);
        $this->assertSame('Oxikbayev', $profile->lastname);
        $this->assertSame('ИТ', $profile->faculty);
        $this->assertSame(28, $profile->facultyid);
        $this->assertSame('univer_10@users.invalid', $profile->email);
        $this->assertTrue($profile->emailistechnical);
        $this->assertStringNotContainsString('950223301098', json_encode($profile));
    }

    public function test_faculty_name_alone_does_not_set_facultyid(): void {
        $transport = new univer_client_test_transport();
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'userId' => 11,
            'data' => ['id' => 11, 'login' => 'u2', 'userType' => 'STUDENT', 'access' => 1],
        ]), ['set-cookie' => ['.ASPXAUTH=x']]));
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'userId' => 11,
            'data' => [
                'sname' => 'X', 'name' => 'Y', 'course' => 1,
            ],
        ])));
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'data' => [[['faculty' => 'Факультет: Бизнес-школа']]],
        ])));
        $client = new univer_client(null, 10, $transport);
        $client->login('u2', 'p');
        $profile = $client->get_profile_for_user(11);
        $this->assertNull($profile->facultyid);
        $this->assertStringContainsString('Бизнес', $profile->faculty);
    }

    public function test_group_list_and_idnumber(): void {
        $transport = new univer_client_test_transport();
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'userId' => 1,
            'data' => ['id' => 1, 'login' => 'u', 'userType' => 'STUDENT', 'access' => 1],
        ]), ['set-cookie' => ['.ASPXAUTH=x']]));
        $transport->enqueue(new http_response(200, json_encode([
            'code' => 0,
            'data' => [[
                'subjectId' => 96453,
                'subjectName' => 'Научное письмо',
                'groupId' => 1794897,
                'planPosId' => 767790,
                'academYear' => 2025,
                'academSemester' => 1,
                'educTypeId' => 2,
                'educTypeName' => 'семинар',
                'teacherId' => 1,
                'langId' => 2,
            ]],
        ])));

        $client = new univer_client(null, 10, $transport);
        $client->login('u', 'p');
        $subjects = $client->get_group_list(2025, 1);
        $this->assertCount(1, $subjects);
        $this->assertSame('UNIVER:96453:2025:1', $subjects[0]->course_idnumber());
        $this->assertSame(
            ['subjectid' => 96453, 'year' => 2025, 'semester' => 1],
            course_idnumber::parse($subjects[0]->course_idnumber())
        );
    }

    public function test_cookie_jar_header(): void {
        $jar = new cookie_jar(['.ASPXAUTH' => 'a', 'ASP.NET_SessionId' => 'b']);
        $this->assertSame('.ASPXAUTH=a; ASP.NET_SessionId=b', $jar->to_header());
        $jar->clear();
        $this->assertTrue($jar->is_empty());
    }

    public function test_server_error_safe_path(): void {
        $transport = new univer_client_test_transport();
        $transport->enqueue(new http_response(500, 'fail'));
        $client = new univer_client(null, 10, $transport);
        try {
            $client->login('u', 'SuperSecretPassword');
            $this->fail('Expected exception');
        } catch (univer_exception $e) {
            $this->assertSame('univer_server_error', $e->errorcode);
            $this->assertStringNotContainsString('SuperSecretPassword', (string) $e->debuginfo);
            $this->assertStringNotContainsString('password=', (string) $e->debuginfo);
        }
    }
}
