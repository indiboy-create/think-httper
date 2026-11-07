<?php

declare(strict_types=1);

$path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$input = file_get_contents('php://input');

$headers = function_exists('getallheaders') ? getallheaders() : [];
if (!is_array($headers) || $headers === []) {
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (str_starts_with((string) $key, 'HTTP_')) {
            $name = str_replace('_', '-', substr((string) $key, 5));
            $headers[$name] = $value;
        }
    }
    if (isset($_SERVER['CONTENT_TYPE'])) {
        $headers['Content-Type'] = $_SERVER['CONTENT_TYPE'];
    }
    if (isset($_SERVER['CONTENT_LENGTH'])) {
        $headers['Content-Length'] = $_SERVER['CONTENT_LENGTH'];
    }
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

if ($path === '/echo') {
    json_response([
        'method' => $method,
        'path' => $path,
        'query' => $_GET,
        'body' => $input,
        'headers' => $headers,
        'cookies' => $_COOKIE,
    ]);
} elseif ($path === '/sleep') {
    $milliseconds = (int) ($_GET['ms'] ?? 100);
    usleep($milliseconds * 1000);
    json_response(['slept' => $milliseconds]);
} elseif ($path === '/status') {
    $status = (int) ($_GET['code'] ?? 500);
    json_response(['status' => $status], $status);
} elseif (str_starts_with($path, '/status/')) {
    $status = (int) substr($path, strlen('/status/'));
    json_response(['status' => $status], $status);
} elseif ($path === '/retry') {
    $key = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($_GET['key'] ?? 'default')) ?: 'default';
    $file = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'think_httper_retry_' . $key . '.txt';
    $count = is_file($file) ? (int) file_get_contents($file) : 0;
    $count++;
    file_put_contents($file, (string) $count);

    $fail = (int) ($_GET['fail'] ?? 2);
    if ($count <= $fail) {
        json_response(['count' => $count, 'failed' => true], 500);
    } else {
        json_response(['count' => $count, 'failed' => false]);
    }
} elseif ($path === '/set-cookie') {
    setcookie('httper_cookie', 'cookie-value', 0, '/');
    json_response(['ok' => true]);
} elseif ($path === '/download') {
    header('Content-Type: text/plain; charset=utf-8');
    echo 'download-content';
} elseif ($path === '/redirect') {
    header('Location: /echo?from=redirect', true, 302);
} else {
    json_response([
        'method' => $method,
        'path' => $path,
        'query' => $_GET,
        'body' => $input,
        'headers' => $headers,
        'cookies' => $_COOKIE,
    ]);
}