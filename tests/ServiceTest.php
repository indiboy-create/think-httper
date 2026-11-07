<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper\Tests;

use think\App;
use Youvision\ThinkHttper\Facade\Httper as HttperFacade;
use Youvision\ThinkHttper\HttperManager;
use Youvision\ThinkHttper\Service;

if (!function_exists('app')) {
    require_once dirname(__DIR__) . '/vendor/topthink/framework/src/helper.php';
}

class ServiceTest extends TestCase
{
    public function test_service_registers_manager_facade_and_helper(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_httper_app_' . bin2hex(random_bytes(4));
        if (!mkdir($root, 0777, true) && !is_dir($root)) {
            $this->fail('无法创建临时应用目录');
        }

        try {
            $app = new App($root);
            $app->config->set([
                'default' => 'default',
                'clients' => [
                    'default' => [
                        'base_uri' => static::$baseUrl,
                    ],
                ],
            ], 'httper');

            $service = new Service($app);
            $service->register();

            $manager = $app->make('httper');
            $this->assertInstanceOf(HttperManager::class, $manager);

            $response = $manager->get('/echo');
            $this->assertTrue($response->ok());
            $this->assertSame('GET', $response->json('method'));

            $response = http_get('/echo');
            $this->assertTrue($response->ok());

            $response = HttperFacade::get('/echo');
            $this->assertTrue($response->ok());
        } finally {
            $this->deleteDirectory($root);
        }
    }

    public function test_service_accepts_top_level_config(): void
    {
        $root = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_httper_app_' . bin2hex(random_bytes(4));
        if (!mkdir($root, 0777, true) && !is_dir($root)) {
            $this->fail('无法创建临时应用目录');
        }

        try {
            $app = new App($root);
            $app->config->set([
                'base_uri' => static::$baseUrl,
                'timeout' => 8,
            ], 'httper');

            $service = new Service($app);
            $service->register();

            $manager = $app->make('httper');
            $this->assertInstanceOf(HttperManager::class, $manager);
            $this->assertSame(8, $manager->client()->getConfig('timeout'));
            $this->assertTrue($manager->get('/echo')->ok());
        } finally {
            $this->deleteDirectory($root);
        }
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($dir);
    }
}