<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Tests;

use PHPUnit\Framework\TestCase;
use Youvision\ThinkHttper\Exception\HttperException;
use Youvision\ThinkHttper\Response;

class ResponseTest extends TestCase
{
    public function test_response_helpers(): void
    {
        $response = new Response(
            data: ['a' => ['b' => 1]],
            raw: '{"a":{"b":1}}',
            status: 200,
            headers: ['Content-Type' => 'application/json'],
            cookies: ['session' => 'abc'],
            effectiveUrl: 'https://example.com/',
            method: 'GET',
            url: 'https://example.com/',
            successful: true,
            error: false
        );

        $this->assertTrue($response->ok());
        $this->assertFalse($response->failed());
        $this->assertFalse($response->isHttpError());
        $this->assertSame(1, $response->json('a.b'));
        $this->assertSame('application/json', $response->header('content-type'));
        $this->assertSame('abc', $response->cookie('session'));
        $this->assertSame('{"a":{"b":1}}', (string) $response);
    }

    public function test_throw_on_failed_response(): void
    {
        $response = new Response(
            status: 500,
            errorCode: 500,
            errorMessage: 'server error',
            successful: false,
            error: true
        );

        try {
            $response->throw();
            $this->fail('预期抛出 HttperException');
        } catch (HttperException $e) {
            $this->assertSame('server error', $e->getMessage());
            $this->assertSame(500, $e->getCode());
            $this->assertSame($response, $e->getResponse());
        }
    }

    public function test_to_array(): void
    {
        $response = new Response(
            data: ['ok' => true],
            status: 200,
            successful: true,
            error: false
        );

        $array = $response->toArray();

        $this->assertSame(['ok' => true], $array['data']);
        $this->assertSame(200, $array['status']);
        $this->assertTrue($array['successful']);
    }
}