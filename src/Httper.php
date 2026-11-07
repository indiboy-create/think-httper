<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper;

use Curl\Curl;
use Psr\Log\LoggerInterface;
use Throwable;
use Traversable;
use Youvision\ThinkHttper\Exception\HttperException;

class Httper
{
    protected array $config;

    protected ?LoggerInterface $logger;

    protected array $headers = [];

    protected array $cookies = [];

    protected array $options = [];

    protected bool $jsonRequest = false;

    public function __construct(array $config = [], ?LoggerInterface $logger = null)
    {
        $this->config = array_replace_recursive(static::defaultConfig(), $config);
        $this->logger = $logger;
    }

    public static function defaultConfig(): array
    {
        return [
            'base_uri' => '',
            'timeout' => 30,
            'connect_timeout' => 10,
            'retry' => 0,
            'retry_delay' => 0,
            'retry_when' => null,
            'headers' => [],
            'user_agent' => '',
            'referer' => '',
            'cookies' => [],
            'cookie_file' => '',
            'cookie_jar' => '',
            'auth' => [
                'type' => '',
                'username' => '',
                'password' => '',
                'token' => '',
            ],
            'proxy' => [
                'host' => '',
                'port' => null,
                'username' => '',
                'password' => '',
                'type' => null,
                'tunnel' => false,
                'auth' => null,
            ],
            'ssl' => [
                'verify_peer' => true,
                'verify_host' => 2,
                'cert' => '',
                'cert_password' => '',
                'key' => '',
            ],
            'options' => [],
            'json' => true,
            'xml' => true,
            'json_request' => false,
            'json_encode_flags' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            'log' => false,
            'log_channel' => 'httper',
            'throw' => false,
            'before' => null,
            'after' => null,
        ];
    }

    public function get(string $url = '', mixed $data = [], array $options = []): Response
    {
        return $this->request('GET', $url, $data, $options);
    }

    public function post(string $url = '', mixed $data = [], array $options = []): Response
    {
        return $this->request('POST', $url, $data, $options);
    }

    public function put(string $url = '', mixed $data = [], array $options = []): Response
    {
        return $this->request('PUT', $url, $data, $options);
    }

    public function patch(string $url = '', mixed $data = [], array $options = []): Response
    {
        return $this->request('PATCH', $url, $data, $options);
    }

    public function delete(string $url = '', mixed $query = [], mixed $data = [], array $options = []): Response
    {
        $options['query'] = $query;
        $options['body'] = $data;

        return $this->request('DELETE', $url, [], $options);
    }

    public function head(string $url = '', mixed $data = [], array $options = []): Response
    {
        return $this->request('HEAD', $url, $data, $options);
    }

    public function options(string $url = '', mixed $data = [], array $options = []): Response
    {
        return $this->request('OPTIONS', $url, $data, $options);
    }

    public function request(string $method, string $url = '', mixed $data = [], array $options = []): Response
    {
        $method = strtoupper($method);
        $config = $this->resolveConfig($options);
        $attempts = max(0, (int) ($config['retry'] ?? 0)) + 1;
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $curl = $this->makeCurl($config);
            $retry = false;

            try {
                $this->invokeBefore($config, $curl, $method, $url, $data);

                $response = $this->send($curl, $method, $url, $data, $options, $config);
                $response->attempts = $attempt;

                $this->invokeAfter($config, $response, $curl);
                $this->logResponse($config, $response);

                if ($attempt < $attempts) {
                    $retry = $this->shouldRetry($config, $response, $attempt);
                    if ($retry) {
                        $this->logRetry($config, $response, $attempt);
                    }
                }
            } catch (Throwable $e) {
                $response = $this->makeErrorResponse($method, $url, $e, $attempt);
                $this->logError($config, $response);

                if ($attempt < $attempts) {
                    $retry = $this->shouldRetryException($config, $response, $attempt);
                    if ($retry) {
                        $this->logRetry($config, $response, $attempt);
                    }
                }
            } finally {
                $curl->close();
            }

            if (!$retry) {
                break;
            }

            $this->sleepBeforeRetry($config);
        }

        if ($response === null) {
            $response = $this->makeErrorResponse($method, $url, new HttperException('HTTP 请求未执行'), 1);
        }

        if (!empty($config['throw']) && $response->failed()) {
            throw new HttperException(
                $response->errorMessage ?: sprintf('HTTP 请求失败（状态码 %d）', $response->status),
                $response->errorCode,
                $response
            );
        }

        return $response;
    }

    public function download(string $url, string $filename, array $options = []): Response
    {
        $config = $this->resolveConfig($options);
        $resolvedUrl = $this->resolveUrl($url, $config);
        $resolvedUrl = $this->applyQuery($resolvedUrl, $options['query'] ?? []);
        $attempts = max(0, (int) ($config['retry'] ?? 0)) + 1;
        $response = null;

        for ($attempt = 1; $attempt <= $attempts; $attempt++) {
            $curl = $this->makeCurl($config);
            $retry = false;

            try {
                $this->invokeBefore($config, $curl, 'DOWNLOAD', $url, null);
                $curl->download($resolvedUrl, $filename);

                $response = $this->makeResponse($curl, 'DOWNLOAD', $resolvedUrl);
                $response->data = is_file($filename) ? $filename : false;
                $response->successful = is_file($filename) && !$curl->error;
                $response->error = !$response->successful;
                $response->attempts = $attempt;

                $this->invokeAfter($config, $response, $curl);
                $this->logResponse($config, $response);

                if ($attempt < $attempts) {
                    $retry = $this->shouldRetry($config, $response, $attempt);
                    if ($retry) {
                        $this->logRetry($config, $response, $attempt);
                    }
                }
            } catch (Throwable $e) {
                $response = $this->makeErrorResponse('DOWNLOAD', $url, $e, $attempt);
                $this->logError($config, $response);

                if ($attempt < $attempts) {
                    $retry = $this->shouldRetryException($config, $response, $attempt);
                    if ($retry) {
                        $this->logRetry($config, $response, $attempt);
                    }
                }
            } finally {
                $curl->close();
            }

            if (!$retry) {
                break;
            }

            if (is_file($filename)) {
                @unlink($filename);
            }

            $this->sleepBeforeRetry($config);
        }

        if ($response === null) {
            $response = $this->makeErrorResponse('DOWNLOAD', $url, new HttperException('HTTP 下载未执行'), 1);
        }

        if (!empty($config['throw']) && $response->failed()) {
            throw new HttperException(
                $response->errorMessage ?: 'HTTP 下载失败',
                $response->errorCode,
                $response
            );
        }

        return $response;
    }

    public function setHeader(string $key, mixed $value): static
    {
        $this->headers[$key] = $value;

        return $this;
    }

    public function setHeaders(array $headers): static
    {
        $this->headers = $this->mergeHeaders($this->headers, $headers);

        return $this;
    }

    public function removeHeader(string $key): static
    {
        foreach (array_keys($this->headers) as $name) {
            if (strcasecmp((string) $name, $key) === 0) {
                unset($this->headers[$name]);
            }
        }

        return $this;
    }

    public function setCookie(string $key, mixed $value): static
    {
        $this->cookies[$key] = (string) $value;

        return $this;
    }

    public function setCookies(array $cookies): static
    {
        foreach ($cookies as $key => $value) {
            $this->setCookie((string) $key, $value);
        }

        return $this;
    }

    public function setOption(int $option, mixed $value): static
    {
        $this->options[$option] = $value;

        return $this;
    }

    public function setOptions(array $options): static
    {
        foreach ($options as $option => $value) {
            $this->setOption((int) $option, $value);
        }

        return $this;
    }

    public function asJson(bool $enabled = true): static
    {
        $this->jsonRequest = $enabled;

        return $this;
    }

    public function clear(): static
    {
        $this->headers = [];
        $this->cookies = [];
        $this->options = [];
        $this->jsonRequest = false;

        return $this;
    }

    public function withConfig(array $config): static
    {
        $clone = clone $this;
        $clone->config = array_replace_recursive($this->config, $config);

        return $clone;
    }

    public function withHeaders(array $headers): static
    {
        $clone = clone $this;
        $clone->headers = $this->mergeHeaders($this->headers, $headers);

        return $clone;
    }

    public function createCurl(array $options = []): Curl
    {
        return $this->makeCurl($this->resolveConfig($options));
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

    protected function makeCurl(array $config): Curl
    {
        $curl = new Curl();
        $this->applyConfig($curl, $config);

        return $curl;
    }

    protected function applyConfig(Curl $curl, array $config): void
    {
        $headers = $this->normalizeHeaders($config['headers'] ?? []);

        if (!empty($config['json_request'])) {
            $headers = $this->ensureHeader($headers, 'Content-Type', 'application/json');
        }

        $auth = is_array($config['auth'] ?? null) ? $config['auth'] : [];
        $authType = strtolower((string) ($auth['type'] ?? ''));

        if ($authType === 'bearer' && !empty($auth['token'])) {
            $headers = $this->ensureHeader($headers, 'Authorization', 'Bearer ' . $auth['token'], true);
        }

        if ($headers !== []) {
            $curl->setHeaders($headers);
        }

        if (!empty($config['cookies']) && is_array($config['cookies'])) {
            $cookies = [];
            foreach ($config['cookies'] as $key => $value) {
                $cookies[(string) $key] = (string) $value;
            }
            $curl->setCookies($cookies);
        }

        if (!empty($config['cookie_file'])) {
            $curl->setCookieFile((string) $config['cookie_file']);
        }

        if (!empty($config['cookie_jar'])) {
            $curl->setCookieJar((string) $config['cookie_jar']);
        }

        if ($authType === 'basic') {
            $curl->setBasicAuthentication((string) ($auth['username'] ?? ''), (string) ($auth['password'] ?? ''));
        } elseif ($authType === 'digest') {
            $curl->setDigestAuthentication((string) ($auth['username'] ?? ''), (string) ($auth['password'] ?? ''));
        } elseif ($authType === 'bearer' && !empty($auth['token'])) {
            if (defined('CURLOPT_XOAUTH2_BEARER')) {
                $curl->setOpt(CURLOPT_XOAUTH2_BEARER, (string) $auth['token']);
            }
        }

        $this->applyProxy($curl, $config['proxy'] ?? []);
        $this->applySsl($curl, $config['ssl'] ?? []);

        if (array_key_exists('timeout', $config) && $config['timeout'] !== null) {
            $this->applyTimeout($curl, $config['timeout'], CURLOPT_TIMEOUT, CURLOPT_TIMEOUT_MS);
        }

        if (array_key_exists('connect_timeout', $config) && $config['connect_timeout'] !== null) {
            $this->applyTimeout($curl, $config['connect_timeout'], CURLOPT_CONNECTTIMEOUT, CURLOPT_CONNECTTIMEOUT_MS);
        }

        if (!empty($config['user_agent'])) {
            $curl->setUserAgent((string) $config['user_agent']);
        }

        if (!empty($config['referer'])) {
            $curl->setReferrer((string) $config['referer']);
        }

        if (array_key_exists('json', $config)) {
            if ($config['json'] === false) {
                $curl->setJsonDecoder(false);
            } elseif (is_callable($config['json'])) {
                $curl->setJsonDecoder($config['json']);
            }
        }

        if (array_key_exists('xml', $config)) {
            if ($config['xml'] === false) {
                $curl->setXmlDecoder(false);
            } elseif (is_callable($config['xml'])) {
                $curl->setXmlDecoder($config['xml']);
            }
        }

        $curl->setOpt(CURLINFO_HEADER_OUT, true);

        if (!empty($config['options']) && is_array($config['options'])) {
            $curl->setOpts($config['options']);
        }
    }

    protected function applyProxy(Curl $curl, mixed $proxy): void
    {
        if (is_string($proxy)) {
            if ($proxy === '') {
                return;
            }
            $proxy = ['host' => $proxy];
        }

        if (!is_array($proxy) || empty($proxy['host'])) {
            return;
        }

        $username = isset($proxy['username']) && $proxy['username'] !== '' ? (string) $proxy['username'] : null;
        $password = isset($proxy['password']) && $proxy['password'] !== '' ? (string) $proxy['password'] : null;
        $port = isset($proxy['port']) && $proxy['port'] !== '' ? (int) $proxy['port'] : null;

        $curl->setProxy((string) $proxy['host'], $port, $username, $password);

        if (!empty($proxy['type'])) {
            $curl->setProxyType($this->normalizeProxyType($proxy['type']));
        }

        if (array_key_exists('tunnel', $proxy)) {
            $curl->setProxyTunnel((bool) $proxy['tunnel']);
        }

        if (!empty($proxy['auth'])) {
            $curl->setProxyAuth((int) $proxy['auth']);
        }
    }

    protected function normalizeProxyType(mixed $type): int
    {
        if (is_int($type)) {
            return $type;
        }

        return match (strtolower((string) $type)) {
            'http' => CURLPROXY_HTTP,
            'https' => defined('CURLPROXY_HTTPS') ? CURLPROXY_HTTPS : CURLPROXY_HTTP,
            'socks4' => CURLPROXY_SOCKS4,
            'socks4a' => CURLPROXY_SOCKS4A,
            'socks5' => CURLPROXY_SOCKS5,
            'socks5_hostname' => CURLPROXY_SOCKS5_HOSTNAME,
            default => CURLPROXY_HTTP,
        };
    }

    protected function applySsl(Curl $curl, mixed $ssl): void
    {
        if (!is_array($ssl)) {
            return;
        }

        if (array_key_exists('verify_peer', $ssl)) {
            $curl->setOpt(CURLOPT_SSL_VERIFYPEER, (bool) $ssl['verify_peer']);
        }

        if (array_key_exists('verify_host', $ssl)) {
            $curl->setOpt(CURLOPT_SSL_VERIFYHOST, (int) $ssl['verify_host']);
        }

        if (!empty($ssl['cert'])) {
            $curl->setOpt(CURLOPT_SSLCERT, (string) $ssl['cert']);
        }

        if (!empty($ssl['cert_password'])) {
            $curl->setOpt(CURLOPT_SSLCERTPASSWD, (string) $ssl['cert_password']);
        }

        if (!empty($ssl['key'])) {
            $curl->setOpt(CURLOPT_SSLKEY, (string) $ssl['key']);
        }
    }

    protected function applyTimeout(Curl $curl, mixed $value, int $secondsOption, int $millisecondsOption): void
    {
        if (is_float($value)) {
            $curl->setOpt($millisecondsOption, (int) round($value * 1000));

            return;
        }

        $curl->setOpt($secondsOption, (int) $value);
    }

    protected function send(Curl $curl, string $method, string $url, mixed $data, array $options, array $config): Response
    {
        $url = $this->resolveUrl($url, $config);
        [$query, $body] = $this->resolvePayload($method, $data, $options, $config);
        $url = $this->applyQuery($url, $query);
        $query = [];

        switch ($method) {
            case 'GET':
                $curl->get($url, $query);
                break;
            case 'POST':
                $curl->post($url, $body ?? '');
                break;
            case 'PUT':
                $curl->put($url, $body ?? '');
                break;
            case 'PATCH':
                $curl->patch($url, $body ?? '');
                break;
            case 'DELETE':
                $curl->delete($url, $query, $body ?? []);
                break;
            case 'HEAD':
                $curl->head($url, $query);
                break;
            case 'OPTIONS':
                $curl->options($url, $query);
                break;
            default:
                throw new HttperException('不支持的 HTTP 方法：' . $method);
        }

        return $this->makeResponse($curl, $method, $url);
    }

    protected function resolvePayload(string $method, mixed $data, array $options, array $config): array
    {
        $queryMethods = ['GET', 'HEAD', 'OPTIONS'];

        if (in_array($method, $queryMethods, true)) {
            $query = $data;
            if (array_key_exists('query', $options)) {
                $extra = $options['query'];
                if (is_array($query) && is_array($extra)) {
                    $query = array_replace($query, $extra);
                } elseif ($extra !== null && $extra !== []) {
                    $query = $extra;
                }
            }

            return [$query, null];
        }

        $body = array_key_exists('body', $options) ? $options['body'] : $data;
        $query = $options['query'] ?? [];

        if (!empty($config['json_request']) && is_array($body)) {
            $encoded = json_encode($body, (int) ($config['json_encode_flags'] ?? 0));
            if ($encoded === false) {
                throw new HttperException('JSON 编码失败：' . json_last_error_msg());
            }
            $body = $encoded;
        }

        return [$query, $body];
    }

    protected function makeResponse(Curl $curl, string $method, string $url): Response
    {
        $error = (bool) $curl->error;

        return new Response(
            data: $curl->response,
            raw: (string) $curl->rawResponse,
            status: (int) $curl->httpStatusCode,
            headers: $this->collectionToArray($curl->responseHeaders),
            cookies: $this->collectionToArray($curl->responseCookies),
            effectiveUrl: (string) ($curl->effectiveUrl ?: $url),
            method: $method,
            url: $url,
            successful: !$error,
            error: $error,
            errorCode: (int) $curl->errorCode,
            errorMessage: $curl->errorMessage !== null ? (string) $curl->errorMessage : null,
            curlError: (bool) $curl->curlError,
            totalTime: (float) $curl->totalTime,
            requestHeaders: (array) $curl->requestHeaders
        );
    }

    protected function makeErrorResponse(string $method, string $url, Throwable $e, int $attempt): Response
    {
        return new Response(
            data: null,
            raw: '',
            status: 0,
            headers: [],
            cookies: [],
            effectiveUrl: $url,
            method: $method,
            url: $url,
            successful: false,
            error: true,
            errorCode: (int) $e->getCode(),
            errorMessage: $e->getMessage(),
            curlError: true,
            totalTime: 0.0,
            requestHeaders: [],
            attempts: $attempt
        );
    }

    protected function resolveUrl(string $url, array $config): string
    {
        $base = trim((string) ($config['base_uri'] ?? ''));

        if ($url === '') {
            return $base;
        }

        if (preg_match('~^[a-z][a-z0-9+.-]*://~i', $url) === 1) {
            return $url;
        }

        if ($base === '') {
            return $url;
        }

        if (str_starts_with($url, '/')) {
            $parts = parse_url($base);
            if ($parts === false || !isset($parts['scheme'], $parts['host'])) {
                return rtrim($base, '/') . '/' . ltrim($url, '/');
            }

            $origin = $parts['scheme'] . '://' . $parts['host'];
            if (isset($parts['port'])) {
                $origin .= ':' . $parts['port'];
            }

            return $origin . $url;
        }

        return rtrim($base, '/') . '/' . ltrim($url, '/');
    }

    protected function appendQuery(string $url, string $query): string
    {
        $query = ltrim($query, '?&');
        if ($query === '') {
            return $url;
        }

        if (strpos($url, '?') === false) {
            return $url . '?' . $query;
        }

        $last = substr($url, -1);
        if ($last === '?' || $last === '&') {
            return $url . $query;
        }

        return $url . '&' . $query;
    }

    protected function applyQuery(string $url, mixed $query): string
    {
        if (is_array($query) && $query !== []) {
            return $this->appendQuery($url, http_build_query($query));
        }

        if (is_string($query) && $query !== '') {
            return $this->appendQuery($url, $query);
        }

        return $url;
    }

    protected function resolveConfig(array $options): array
    {
        $config = $this->config;

        $config['headers'] = $this->mergeHeaders($config['headers'] ?? [], $this->headers);
        $config['cookies'] = array_replace(
            is_array($config['cookies'] ?? null) ? $config['cookies'] : [],
            $this->cookies
        );
        $config['options'] = array_replace(
            is_array($config['options'] ?? null) ? $config['options'] : [],
            $this->options
        );

        $special = ['headers', 'query', 'body', 'json_request'];
        $overrides = array_diff_key($options, array_flip($special));
        $config = array_replace_recursive($config, $overrides);
        $config['headers'] = $this->mergeHeaders($config['headers'] ?? [], $options['headers'] ?? []);

        if (array_key_exists('json_request', $options)) {
            $config['json_request'] = (bool) $options['json_request'];
        } elseif ($this->jsonRequest) {
            $config['json_request'] = true;
        }

        return $config;
    }

    protected function normalizeHeaders(mixed $headers): array
    {
        if (!is_array($headers)) {
            return [];
        }

        $result = [];
        foreach ($headers as $key => $value) {
            if (is_int($key)) {
                if (!is_string($value)) {
                    continue;
                }
                $parts = explode(':', $value, 2);
                $name = trim($parts[0]);
                $val = isset($parts[1]) ? trim($parts[1]) : '';
            } else {
                $name = trim((string) $key);
                $val = $value;
            }

            if ($name !== '') {
                if (!is_scalar($val) && $val !== null) {
                    continue;
                }
                $result[$name] = (string) $val;
            }
        }

        return $result;
    }

    protected function mergeHeaders(mixed $base, mixed $extra): array
    {
        $result = $this->normalizeHeaders($base);

        foreach ($this->normalizeHeaders($extra) as $name => $value) {
            foreach (array_keys($result) as $existing) {
                if (strcasecmp((string) $existing, (string) $name) === 0) {
                    unset($result[$existing]);
                }
            }
            $result[$name] = $value;
        }

        return $result;
    }

    protected function ensureHeader(array $headers, string $name, string $value, bool $overwrite = false): array
    {
        foreach ($headers as $key => $existing) {
            if (strcasecmp((string) $key, $name) === 0) {
                if ($overwrite) {
                    $headers[$key] = $value;
                }

                return $headers;
            }
        }

        $headers[$name] = $value;

        return $headers;
    }

    protected function collectionToArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if ($value instanceof Traversable) {
            return iterator_to_array($value);
        }

        return [];
    }

    protected function invokeBefore(array $config, Curl $curl, string $method, string $url, mixed $data): void
    {
        $before = $config['before'] ?? null;
        if (is_callable($before)) {
            $before($curl, $method, $url, $data, $config);
        }
    }

    protected function invokeAfter(array $config, Response $response, Curl $curl): void
    {
        $after = $config['after'] ?? null;
        if (is_callable($after)) {
            $after($response, $curl, $config);
        }
    }

    protected function shouldRetry(array $config, Response $response, int $attempt): bool
    {
        if ($response->successful) {
            return false;
        }

        $decider = $config['retry_when'] ?? null;
        if (is_callable($decider)) {
            return (bool) $decider($response, $attempt);
        }

        return $response->curlError
            || $response->status === 0
            || $response->status >= 500
            || $response->status === 429;
    }

    protected function shouldRetryException(array $config, Response $response, int $attempt): bool
    {
        $decider = $config['retry_when'] ?? null;
        if (is_callable($decider)) {
            return (bool) $decider($response, $attempt);
        }

        return false;
    }

    protected function sleepBeforeRetry(array $config): void
    {
        $delay = (int) ($config['retry_delay'] ?? 0);
        if ($delay > 0) {
            usleep($delay * 1000);
        }
    }

    protected function logResponse(array $config, Response $response): void
    {
        if (empty($config['log'])) {
            return;
        }

        $context = $this->logContext($response);
        if ($response->successful) {
            $this->writeLog($config, 'info', 'HTTP 请求完成', $context);
        } else {
            $this->writeLog($config, 'error', 'HTTP 请求失败', $context);
        }
    }

    protected function logError(array $config, Response $response): void
    {
        if (empty($config['log'])) {
            return;
        }

        $this->writeLog($config, 'error', 'HTTP 请求异常', $this->logContext($response));
    }

    protected function logRetry(array $config, Response $response, int $attempt): void
    {
        if (empty($config['log'])) {
            return;
        }

        $context = $this->logContext($response);
        $context['retry_attempt'] = $attempt;
        $this->writeLog($config, 'warning', 'HTTP 请求重试', $context);
    }

    protected function logContext(Response $response): array
    {
        return [
            'method' => $response->method,
            'url' => $response->url,
            'status' => $response->status,
            'total_time' => $response->totalTime,
            'attempts' => $response->attempts,
            'error_code' => $response->errorCode,
            'error_message' => $response->errorMessage,
        ];
    }

    protected function writeLog(array $config, string $level, string $message, array $context): void
    {
        if ($this->logger === null) {
            return;
        }

        $logger = $this->logger;
        $channel = (string) ($config['log_channel'] ?? 'httper');

        try {
            if ($channel !== '' && method_exists($logger, 'channel')) {
                $logger = $logger->channel($channel);
            }

            $logger->log($level, $message, $context);
        } catch (Throwable $e) {
            try {
                $this->logger->log($level, $message, $context);
            } catch (Throwable $ignored) {

            }
        }
    }
}