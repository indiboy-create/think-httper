<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Tests;

use Youvision\ThinkHttper\Exception\HttperException;
use Youvision\ThinkHttper\Httper;
use Youvision\ThinkHttper\Response;
use Youvision\ThinkHttper\Tests\Support\ArrayLogger;

class HttperTest extends TestCase
{
    public function test_get_request_and_query(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl]);

        $response = $http->get('/echo', ['foo' => 'bar']);

        $this->assertTrue($response->ok());
        $this->assertSame(200, $response->status);
        $this->assertSame('GET', $response->json('method'));
        $this->assertSame('bar', $response->json('query.foo'));
        $this->assertNotEmpty($response->requestHeaders);
    }

    public function test_base_uri_relative_path(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl . '/api/']);

        $response = $http->get('echo');

        $this->assertSame('/api/echo', $response->json('path'));
    }

    public function test_post_form(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl]);

        $response = $http->post('/echo', ['a' => '1', 'b' => '2']);

        $this->assertSame('POST', $response->json('method'));
        $this->assertStringContainsString('a=1', (string) $response->json('body'));
        $this->assertStringContainsString('b=2', (string) $response->json('body'));
    }

    public function test_post_json(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl]);

        $response = $http->asJson()->post('/echo', ['name' => '张三']);

        $this->assertSame('{"name":"张三"}', $response->json('body'));
        $this->assertStringContainsString('application/json', (string) $response->json('headers.Content-Type'));
    }

    public function test_headers_and_basic_auth(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'headers' => ['X-Test' => 'abc'],
            'auth' => [
                'type' => 'basic',
                'username' => 'user',
                'password' => 'pass',
            ],
        ]);

        $response = $http->get('/echo');

        $this->assertSame('abc', $response->json('headers.X-Test'));
        $this->assertSame('Basic ' . base64_encode('user:pass'), $response->json('headers.Authorization'));
    }

    public function test_bearer_auth(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'auth' => [
                'type' => 'bearer',
                'token' => 'token-123',
            ],
        ]);

        $response = $http->get('/echo');

        $this->assertSame('Bearer token-123', $response->json('headers.Authorization'));
    }

    public function test_timeout(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'timeout' => 0.1,
            'connect_timeout' => 0.1,
        ]);

        $response = $http->get('/sleep', ['ms' => 400]);

        $this->assertTrue($response->failed());
        $this->assertTrue($response->curlError);
    }

    public function test_retry(): void
    {
        $key = 'retry' . bin2hex(random_bytes(4));
        $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_httper_retry_' . $key . '.txt';
        @unlink($file);

        try {
            $http = new Httper([
                'base_uri' => static::$baseUrl,
                'retry' => 2,
                'retry_delay' => 10,
            ]);

            $response = $http->get('/retry', ['key' => $key, 'fail' => 2]);

            $this->assertTrue($response->ok());
            $this->assertSame(3, $response->attempts);
            $this->assertSame(3, $response->json('count'));
        } finally {
            @unlink($file);
        }
    }

    public function test_throw_on_failure(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'throw' => true,
        ]);

        try {
            $http->get('/status', ['code' => 500]);
            $this->fail('预期抛出 HttperException');
        } catch (HttperException $e) {
            $this->assertNotNull($e->getResponse());
            $this->assertSame(500, $e->getResponse()->status);
        }
    }

    public function test_json_decoder_disabled(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'json' => false,
        ]);

        $response = $http->get('/echo');

        $this->assertIsString($response->data);
        $this->assertStringContainsString('"method":"GET"', $response->data);
    }

    public function test_custom_json_decoder(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'json' => static function (string $json): array {
                return ['decoded' => json_decode($json, true)];
            },
        ]);

        $response = $http->get('/echo');

        $this->assertSame('GET', $response->json('decoded.method'));
    }

    public function test_cookies(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'cookies' => ['foo' => 'bar'],
        ]);

        $response = $http->get('/echo');

        $this->assertSame('bar', $response->json('cookies.foo'));
    }

    public function test_config_is_applied_to_curl(): void
    {
        $http = new Httper([
            'timeout' => 12,
            'connect_timeout' => 3,
            'user_agent' => 'TestAgent',
            'referer' => 'https://ref.example/',
            'headers' => ['X-Test' => '1'],
            'cookies' => ['foo' => 'bar'],
            'cookie_file' => '/tmp/cookie.txt',
            'cookie_jar' => '/tmp/jar.txt',
            'auth' => [
                'type' => 'basic',
                'username' => 'user',
                'password' => 'pass',
            ],
            'proxy' => [
                'host' => '127.0.0.1',
                'port' => 8080,
                'type' => 'http',
                'tunnel' => true,
                'auth' => CURLAUTH_BASIC,
            ],
            'ssl' => [
                'verify_peer' => false,
                'verify_host' => 0,
                'cert' => '/tmp/cert.pem',
                'cert_password' => 'secret',
                'key' => '/tmp/key.pem',
            ],
            'options' => [
                CURLOPT_FOLLOWLOCATION => true,
            ],
        ]);

        $curl = $http->createCurl();

        try {
            $this->assertSame(12, $curl->getOpt(CURLOPT_TIMEOUT));
            $this->assertSame(3, $curl->getOpt(CURLOPT_CONNECTTIMEOUT));
            $this->assertSame('TestAgent', $curl->getOpt(CURLOPT_USERAGENT));
            $this->assertSame('https://ref.example/', $curl->getOpt(CURLOPT_REFERER));
            $this->assertSame(['X-Test: 1'], $curl->getOpt(CURLOPT_HTTPHEADER));
            $this->assertSame('foo=bar', $curl->getOpt(CURLOPT_COOKIE));
            $this->assertSame('/tmp/cookie.txt', $curl->getOpt(CURLOPT_COOKIEFILE));
            $this->assertSame('/tmp/jar.txt', $curl->getOpt(CURLOPT_COOKIEJAR));
            $this->assertSame(CURLAUTH_BASIC, $curl->getOpt(CURLOPT_HTTPAUTH));
            $this->assertSame('user:pass', $curl->getOpt(CURLOPT_USERPWD));
            $this->assertSame('127.0.0.1', $curl->getOpt(CURLOPT_PROXY));
            $this->assertSame(8080, $curl->getOpt(CURLOPT_PROXYPORT));
            $this->assertSame(CURLPROXY_HTTP, $curl->getOpt(CURLOPT_PROXYTYPE));
            $this->assertTrue($curl->getOpt(CURLOPT_HTTPPROXYTUNNEL));
            $this->assertSame(CURLAUTH_BASIC, $curl->getOpt(CURLOPT_PROXYAUTH));
            $this->assertFalse($curl->getOpt(CURLOPT_SSL_VERIFYPEER));
            $this->assertSame(0, $curl->getOpt(CURLOPT_SSL_VERIFYHOST));
            $this->assertSame('/tmp/cert.pem', $curl->getOpt(CURLOPT_SSLCERT));
            $this->assertSame('secret', $curl->getOpt(CURLOPT_SSLCERTPASSWD));
            $this->assertSame('/tmp/key.pem', $curl->getOpt(CURLOPT_SSLKEY));
            $this->assertTrue($curl->getOpt(CURLOPT_FOLLOWLOCATION));
        } finally {
            $curl->close();
        }
    }

    public function test_float_timeout_uses_milliseconds(): void
    {
        $http = new Httper([
            'timeout' => 0.25,
            'connect_timeout' => 0.5,
        ]);

        $curl = $http->createCurl();

        try {
            $this->assertSame(250, $curl->getOpt(CURLOPT_TIMEOUT_MS));
            $this->assertSame(500, $curl->getOpt(CURLOPT_CONNECTTIMEOUT_MS));
        } finally {
            $curl->close();
        }
    }

    public function test_request_options_override_config(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'headers' => ['X-Default' => 'a'],
            'timeout' => 30,
        ]);

        $response = $http->get('/echo', [], [
            'headers' => ['X-Default' => 'b'],
            'query' => ['q' => '1'],
            'timeout' => 5,
        ]);

        $this->assertSame('b', $response->json('headers.X-Default'));
        $this->assertSame('1', $response->json('query.q'));

        $curl = $http->createCurl(['timeout' => 5]);
        try {
            $this->assertSame(5, $curl->getOpt(CURLOPT_TIMEOUT));
        } finally {
            $curl->close();
        }
    }

    public function test_runtime_headers_and_clear(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl]);
        $http->setHeader('X-Runtime', '1');

        $response = $http->get('/echo');
        $this->assertSame('1', $response->json('headers.X-Runtime'));

        $http->clear();

        $response = $http->get('/echo');
        $headers = (array) $response->json('headers', []);
        $this->assertArrayNotHasKey('X-Runtime', $headers);
    }

    public function test_before_and_after_callbacks(): void
    {
        $beforeCalled = false;
        $afterCalled = false;

        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'before' => static function () use (&$beforeCalled): void {
                $beforeCalled = true;
            },
            'after' => static function (Response $response) use (&$afterCalled): void {
                $afterCalled = true;
            },
        ]);

        $http->get('/echo');

        $this->assertTrue($beforeCalled);
        $this->assertTrue($afterCalled);
    }

    public function test_log(): void
    {
        $logger = new ArrayLogger();
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'log' => true,
            'log_channel' => 'httper',
        ], $logger);

        $http->get('/echo');

        $this->assertNotEmpty($logger->records);
        $this->assertSame('info', $logger->records[0]['level']);
        $this->assertSame('GET', $logger->records[0]['context']['method']);
    }

    public function test_redirect_follow_option(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'options' => [
                CURLOPT_FOLLOWLOCATION => true,
            ],
        ]);

        $response = $http->get('/redirect');

        $this->assertSame('redirect', $response->json('query.from'));
        $this->assertStringContainsString('/echo?from=redirect', $response->effectiveUrl);
    }

    public function test_download(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'httper_download_');
        $this->assertNotFalse($file);

        try {
            $http = new Httper(['base_uri' => static::$baseUrl]);
            $response = $http->download('/download', $file);

            $this->assertTrue($response->ok());
            $this->assertSame('download-content', file_get_contents($file));
        } finally {
            @unlink($file);
        }
    }

    public function test_xml_decoder_disabled_mapping(): void
    {
        $http = new Httper(['xml' => false]);
        $curl = $http->createCurl();

        try {
            $this->assertFalse($curl->getXmlDecoder());
        } finally {
            $curl->close();
        }
    }

    public function test_options_have_highest_priority(): void
    {
        $http = new Httper([
            'timeout' => 10,
            'options' => [
                CURLOPT_TIMEOUT => 3,
            ],
        ]);

        $curl = $http->createCurl();

        try {
            $this->assertSame(3, $curl->getOpt(CURLOPT_TIMEOUT));
        } finally {
            $curl->close();
        }
    }

    public function test_user_agent_and_referer_are_sent(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'user_agent' => 'UA-Test',
            'referer' => 'https://ref.example/',
        ]);

        $response = $http->get('/echo');
        $headers = array_change_key_case((array) $response->json('headers', []), CASE_LOWER);

        $this->assertSame('UA-Test', $headers['user-agent']);
        $this->assertSame('https://ref.example/', $headers['referer']);
    }

    public function test_digest_auth_mapping(): void
    {
        $http = new Httper([
            'auth' => [
                'type' => 'digest',
                'username' => 'user',
                'password' => 'pass',
            ],
        ]);

        $curl = $http->createCurl();

        try {
            $this->assertSame(CURLAUTH_DIGEST, $curl->getOpt(CURLOPT_HTTPAUTH));
            $this->assertSame('user:pass', $curl->getOpt(CURLOPT_USERPWD));
        } finally {
            $curl->close();
        }
    }

    public function test_json_encode_flags(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'json_encode_flags' => 0,
        ]);

        $response = $http->asJson()->post('/echo', ['name' => '张三']);

        $this->assertSame('{"name":"\u5f20\u4e09"}', $response->json('body'));
    }

    public function test_request_level_json_request(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl]);

        $response = $http->post('/echo', ['a' => 1], ['json_request' => true]);

        $this->assertSame('{"a":1}', $response->json('body'));
        $this->assertStringContainsString('application/json', (string) $response->json('headers.Content-Type'));
    }

    public function test_post_query_option(): void
    {
        $http = new Httper(['base_uri' => static::$baseUrl]);

        $response = $http->post('/echo', ['a' => 1], ['query' => ['q' => 2]]);

        $this->assertSame('2', $response->json('query.q'));
        $this->assertStringContainsString('a=1', (string) $response->json('body'));
    }

    public function test_cookie_jar_persistence(): void
    {
        $jar = tempnam(sys_get_temp_dir(), 'httper_jar_');
        $this->assertNotFalse($jar);

        try {
            $first = new Httper([
                'base_uri' => static::$baseUrl,
                'cookie_file' => $jar,
                'cookie_jar' => $jar,
            ]);
            $first->get('/set-cookie');

            $second = new Httper([
                'base_uri' => static::$baseUrl,
                'cookie_file' => $jar,
                'cookie_jar' => $jar,
            ]);
            $response = $second->get('/echo');

            $this->assertSame('cookie-value', $response->json('cookies.httper_cookie'));
        } finally {
            @unlink($jar);
        }
    }

    public function test_retry_delay(): void
    {
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'retry' => 1,
            'retry_delay' => 100,
        ]);

        $start = microtime(true);
        $response = $http->get('/status', ['code' => 500]);
        $elapsed = (microtime(true) - $start) * 1000;

        $this->assertTrue($response->failed());
        $this->assertSame(2, $response->attempts);
        $this->assertGreaterThanOrEqual(90.0, $elapsed);
    }

    public function test_retry_when_can_disable_retry(): void
    {
        $calls = 0;

        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'retry' => 2,
            'retry_when' => static function (Response $response, int $attempt) use (&$calls): bool {
                $calls++;

                return false;
            },
        ]);

        $response = $http->get('/status', ['code' => 500]);

        $this->assertSame(1, $response->attempts);
        $this->assertSame(1, $calls);
    }

    public function test_log_channel(): void
    {
        $logger = new ArrayLogger();
        $http = new Httper([
            'base_uri' => static::$baseUrl,
            'log' => true,
            'log_channel' => 'custom-channel',
        ], $logger);

        $http->get('/echo');

        $this->assertSame(['custom-channel'], $logger->channels);
    }
}