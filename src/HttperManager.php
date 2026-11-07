<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper;

use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/**
 * @method Response get(string $url = '', mixed $data = [], array $options = [])
 * @method Response post(string $url = '', mixed $data = [], array $options = [])
 * @method Response put(string $url = '', mixed $data = [], array $options = [])
 * @method Response patch(string $url = '', mixed $data = [], array $options = [])
 * @method Response delete(string $url = '', mixed $query = [], mixed $data = [], array $options = [])
 * @method Response head(string $url = '', mixed $data = [], array $options = [])
 * @method Response options(string $url = '', mixed $data = [], array $options = [])
 * @method Response request(string $method, string $url = '', mixed $data = [], array $options = [])
 * @method Response download(string $url, string $filename, array $options = [])
 * @method Httper asJson(bool $enabled = true)
 * @method Httper setHeader(string $key, mixed $value)
 * @method Httper setHeaders(array $headers)
 * @method Httper setCookie(string $key, mixed $value)
 * @method Httper setCookies(array $cookies)
 * @method Httper setOption(int $option, mixed $value)
 * @method Httper setOptions(array $options)
 * @method Httper clear()
 */
class HttperManager
{
    protected array $config;

    protected ?LoggerInterface $logger;

    /** @var array<string, Httper> */
    protected array $clients = [];

    /** @var array<string, callable> */
    protected array $extenders = [];

    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = $config;
        $this->logger = $logger;
    }

    public function client(?string $name = null): Httper
    {
        $name = $name ?: $this->getDefaultClientName();

        if (!isset($this->clients[$name])) {
            $this->clients[$name] = $this->createClient($name);
        }

        return $this->clients[$name];
    }

    public function getDefaultClient(): Httper
    {
        return $this->client();
    }

    public function getDefaultClientName(): string
    {
        $name = $this->config['default'] ?? 'default';

        return is_string($name) && $name !== '' ? $name : 'default';
    }

    public function extend(string $name, callable $factory): static
    {
        $this->extenders[$name] = $factory;
        unset($this->clients[$name]);

        return $this;
    }

    public function forget(?string $name = null): static
    {
        if ($name === null) {
            $this->clients = [];
        } else {
            unset($this->clients[$name]);
        }

        return $this;
    }

    public function getConfig(?string $key = null, mixed $default = null): mixed
    {
        if ($key === null || $key === '') {
            return $this->config;
        }

        $value = $this->config;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

    public function setLogger(?LoggerInterface $logger): static
    {
        $this->logger = $logger;
        $this->clients = [];

        return $this;
    }

    public function resolveClientConfig(string $name): array
    {
        $defaults = is_array($this->config['defaults'] ?? null) ? $this->config['defaults'] : [];
        $clients = $this->config['clients'] ?? null;

        if (is_array($clients)) {
            if (!array_key_exists($name, $clients)) {
                throw new InvalidArgumentException(sprintf('HTTP 客户端 [%s] 未配置', $name));
            }
            if (!is_array($clients[$name])) {
                throw new InvalidArgumentException(sprintf('HTTP 客户端 [%s] 的配置必须是数组', $name));
            }
            $client = $clients[$name];
        } else {
            if ($name !== $this->getDefaultClientName()) {
                throw new InvalidArgumentException(sprintf('HTTP 客户端 [%s] 未配置', $name));
            }

            $client = $this->config;
            unset($client['default'], $client['defaults'], $client['clients']);
        }

        return array_replace_recursive(Httper::defaultConfig(), $defaults, $client);
    }

    protected function createClient(string $name): Httper
    {
        if (isset($this->extenders[$name])) {
            $client = ($this->extenders[$name])($this);
            if (!$client instanceof Httper) {
                throw new InvalidArgumentException(sprintf('HTTP 客户端 [%s] 的扩展必须返回 %s 实例', $name, Httper::class));
            }

            return $client;
        }

        return new Httper($this->resolveClientConfig($name), $this->logger);
    }

    public function __call(string $method, array $parameters): mixed
    {
        return $this->client()->{$method}(...$parameters);
    }
}