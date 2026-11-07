<?php

declare(strict_types=1);

use Youvision\ThinkHttper\Httper;
use Youvision\ThinkHttper\Response;

if (!function_exists('http')) {
    function http(?string $client = null): Httper
    {
        return app('httper')->client($client);
    }
}

if (!function_exists('http_client')) {
    function http_client(?string $client = null): Httper
    {
        return http($client);
    }
}

if (!function_exists('http_request')) {
    function http_request(
        string $method,
        string $url = '',
        mixed $data = [],
        array $options = [],
        ?string $client = null
    ): Response {
        return http($client)->request($method, $url, $data, $options);
    }
}

if (!function_exists('http_get')) {
    function http_get(string $url = '', mixed $data = [], array $options = [], ?string $client = null): Response
    {
        return http($client)->get($url, $data, $options);
    }
}

if (!function_exists('http_post')) {
    function http_post(string $url = '', mixed $data = [], array $options = [], ?string $client = null): Response
    {
        return http($client)->post($url, $data, $options);
    }
}

if (!function_exists('http_put')) {
    function http_put(string $url = '', mixed $data = [], array $options = [], ?string $client = null): Response
    {
        return http($client)->put($url, $data, $options);
    }
}

if (!function_exists('http_patch')) {
    function http_patch(string $url = '', mixed $data = [], array $options = [], ?string $client = null): Response
    {
        return http($client)->patch($url, $data, $options);
    }
}

if (!function_exists('http_delete')) {
    function http_delete(
        string $url = '',
        mixed $query = [],
        mixed $data = [],
        array $options = [],
        ?string $client = null
    ): Response {
        return http($client)->delete($url, $query, $data, $options);
    }
}

if (!function_exists('http_head')) {
    function http_head(string $url = '', mixed $data = [], array $options = [], ?string $client = null): Response
    {
        return http($client)->head($url, $data, $options);
    }
}

if (!function_exists('http_options')) {
    function http_options(string $url = '', mixed $data = [], array $options = [], ?string $client = null): Response
    {
        return http($client)->options($url, $data, $options);
    }
}

if (!function_exists('http_download')) {
    function http_download(
        string $url,
        string $filename,
        array $options = [],
        ?string $client = null
    ): Response {
        return http($client)->download($url, $filename, $options);
    }
}