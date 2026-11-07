<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper;

use Psr\Log\LoggerInterface;
use think\Service as BaseService;
use Throwable;

class Service extends BaseService
{
    public function register(): void
    {
        $default = require __DIR__ . '/../config/httper.php';
        $config = $this->app->config->get('httper', []);

        if (!is_array($config)) {
            $config = [];
        }

        if ($config !== [] && !array_key_exists('clients', $config) && !array_key_exists('defaults', $config)) {
            $client = $config;
            unset($client['default']);
            $config = $default;
            $config['clients']['default'] = array_replace_recursive(
                $config['clients']['default'] ?? [],
                $client
            );
        } else {
            $config = array_replace_recursive($default, $config);
        }

        $this->app->config->set($config, 'httper');

        $logger = null;
        try {
            $logger = $this->app->make('log');
        } catch (Throwable $e) {
            $logger = null;
        }

        $manager = new HttperManager($config, $logger instanceof LoggerInterface ? $logger : null);

        $this->app->instance('httper', $manager);
        $this->app->instance(HttperManager::class, $manager);
    }
}