<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Tests;

use InvalidArgumentException;
use Youvision\ThinkHttper\Httper;
use Youvision\ThinkHttper\HttperManager;
use Youvision\ThinkHttper\Service;

class ManagerTest extends TestCase
{
    public function test_default_and_named_clients(): void
    {
        $manager = new HttperManager([
            'default' => 'default',
            'defaults' => [
                'timeout' => 5,
            ],
            'clients' => [
                'default' => [
                    'base_uri' => static::$baseUrl,
                ],
                'other' => [
                    'base_uri' => static::$baseUrl,
                    'headers' => ['X-Client' => 'other'],
                ],
            ],
        ]);

        $response = $manager->client('other')->get('/echo');
        $this->assertSame('other', $response->json('headers.X-Client'));

        $response = $manager->get('/echo');
        $this->assertSame('GET', $response->json('method'));

        $this->assertSame(5, $manager->client('default')->getConfig('timeout'));
    }

    public function test_top_level_config_as_default_client(): void
    {
        $manager = new HttperManager([
            'base_uri' => static::$baseUrl,
            'timeout' => 8,
        ]);

        $response = $manager->get('/echo');

        $this->assertTrue($response->ok());
        $this->assertSame(8, $manager->client()->getConfig('timeout'));
    }

    public function test_extend(): void
    {
        $manager = new HttperManager([
            'clients' => [
                'default' => [
                    'base_uri' => static::$baseUrl,
                ],
            ],
        ]);

        $manager->extend('custom', static function (HttperManager $manager): Httper {
            return new Httper(['base_uri' => static::$baseUrl]);
        });

        $response = $manager->client('custom')->get('/echo');

        $this->assertTrue($response->ok());
    }

    public function test_unknown_client_throws(): void
    {
        $manager = new HttperManager([
            'clients' => [
                'default' => [],
            ],
        ]);

        $this->expectException(InvalidArgumentException::class);

        $manager->client('missing');
    }

    public function test_forget_client(): void
    {
        $manager = new HttperManager([
            'clients' => [
                'default' => [
                    'base_uri' => static::$baseUrl,
                ],
            ],
        ]);

        $first = $manager->client();
        $manager->forget();
        $second = $manager->client();

        $this->assertNotSame($first, $second);
    }

    public function test_service_class(): void
    {
        $this->assertTrue(is_subclass_of(Service::class, \think\Service::class));
    }

    public function test_helper_functions_exist(): void
    {
        $this->assertTrue(function_exists('http'));
        $this->assertTrue(function_exists('http_client'));
        $this->assertTrue(function_exists('http_request'));
        $this->assertTrue(function_exists('http_get'));
        $this->assertTrue(function_exists('http_post'));
        $this->assertTrue(function_exists('http_put'));
        $this->assertTrue(function_exists('http_patch'));
        $this->assertTrue(function_exists('http_delete'));
        $this->assertTrue(function_exists('http_head'));
        $this->assertTrue(function_exists('http_options'));
        $this->assertTrue(function_exists('http_download'));
    }
}