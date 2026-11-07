<?php

declare(strict_types=1);

namespace Youvision\ThinkHttper;

use Youvision\ThinkHttper\Exception\HttperException;

class Response
{
    public function __construct(
        public mixed $data = null,
        public string $raw = '',
        public int $status = 0,
        public array $headers = [],
        public array $cookies = [],
        public string $effectiveUrl = '',
        public string $method = '',
        public string $url = '',
        public bool $successful = false,
        public bool $error = true,
        public int $errorCode = 0,
        public ?string $errorMessage = null,
        public bool $curlError = false,
        public float $totalTime = 0.0,
        public array $requestHeaders = [],
        public int $attempts = 1
    ) {
    }

    public function ok(): bool
    {
        return $this->successful;
    }

    public function failed(): bool
    {
        return !$this->successful;
    }

    public function isHttpError(): bool
    {
        return $this->status >= 400;
    }

    public function header(string $name, mixed $default = null): mixed
    {
        foreach ($this->headers as $key => $value) {
            if (strcasecmp((string) $key, $name) === 0) {
                return $value;
            }
        }

        return $default;
    }

    public function cookie(string $name, mixed $default = null): mixed
    {
        return $this->cookies[$name] ?? $default;
    }

    public function json(?string $key = null, mixed $default = null): mixed
    {
        $data = $this->data;

        if ($key === null || $key === '') {
            return $data;
        }

        if (is_object($data)) {
            $data = get_object_vars($data);
        }

        if (!is_array($data)) {
            return $default;
        }

        $value = $data;
        foreach (explode('.', $key) as $segment) {
            if (is_object($value)) {
                $value = get_object_vars($value);
            }

            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }

    public function throw(): static
    {
        if ($this->failed()) {
            throw new HttperException(
                $this->errorMessage ?: sprintf('HTTP 请求失败（状态码 %d）', $this->status),
                $this->errorCode,
                $this
            );
        }

        return $this;
    }

    public function toArray(): array
    {
        return [
            'data' => $this->data,
            'raw' => $this->raw,
            'status' => $this->status,
            'headers' => $this->headers,
            'cookies' => $this->cookies,
            'effective_url' => $this->effectiveUrl,
            'method' => $this->method,
            'url' => $this->url,
            'successful' => $this->successful,
            'error' => $this->error,
            'error_code' => $this->errorCode,
            'error_message' => $this->errorMessage,
            'curl_error' => $this->curlError,
            'total_time' => $this->totalTime,
            'request_headers' => $this->requestHeaders,
            'attempts' => $this->attempts,
        ];
    }

    public function __toString(): string
    {
        return $this->raw;
    }
}