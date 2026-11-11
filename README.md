## ThinkPHP-PanGu - 盘古ThinkPHP开源索引!
面向 ThinkPHP 的开源工程索引：组件、框架、服务与发布工具，按能力组织，持续整理。
仓库地址：**<https://github.com/indiboy-create/ThinkPHP-PanGu/>**
在线站点：**<https://indiboy-create.github.io/ThinkPHP-PanGu/>**

# think-httper

基于 [php-curl-class](https://github.com/php-curl-class/php-curl-class) 封装的 ThinkPHP 8 HTTP 客户端，提供 Facade、助手函数、多客户端、配置合并、重试、日志、异常和统一响应对象。

## 环境要求

- PHP >= 8.0
- ext-curl
- ThinkPHP >= 8.0
- php-curl-class ^13.0

## 安装

```bash
composer require youvision/think-httper
```

ThinkPHP 会根据 `composer.json` 中的 `extra.think.services` 自动注册 `Youvision\ThinkHttper\Service`。

## 配置

包内置默认配置，未发布配置文件时也可以直接使用。需要修改配置时，将配置文件复制到项目 `config` 目录：

```bash
cp vendor/youvision/think-httper/config/httper.php config/httper.php
```

配置文件中的每个参数都带有中文说明，主要包括：

- `base_uri`、`timeout`、`connect_timeout`
- `retry`、`retry_delay`、`retry_when`
- `headers`、`user_agent`、`referer`
- `cookies`、`cookie_file`、`cookie_jar`
- `auth`、`proxy`、`ssl`
- `json`、`xml`、`json_request`、`json_encode_flags`
- `log`、`log_channel`、`throw`
- `before`、`after`
- `options`

只使用一个客户端时，也可以直接使用顶层配置：

```php
return [
    'base_uri' => 'https://api.example.com/',
    'timeout' => 10,
];
```

## 快速开始

### Facade

```php
use Youvision\ThinkHttper\Facade\Httper;

$response = Httper::get('https://api.example.com/users');

$response = Httper::post('https://api.example.com/users', [
    'name' => '张三',
]);

if ($response->ok()) {
    $data = $response->json();
    $status = $response->status;
}
```

### 助手函数

```php
$response = http_get('https://api.example.com/users');

$response = http_post('https://api.example.com/users', [
    'name' => '张三',
]);

$response = http('api')->get('/users');
```

可用助手函数：

- `http()`、`http_client()`
- `http_request()`
- `http_get()`、`http_post()`、`http_put()`、`http_patch()`
- `http_delete()`、`http_head()`、`http_options()`
- `http_download()`

### 依赖注入

```php
use Youvision\ThinkHttper\HttperManager;

class UserController
{
    public function index(HttperManager $http)
    {
        $response = $http->get('https://api.example.com/users');

        return json($response->json());
    }
}
```

`HttperManager` 是容器单例；`Httper` 客户端实例由 `HttperManager` 按名称缓存。

## 多客户端

```php
// config/httper.php
return [
    'default' => 'default',

    'clients' => [
        'default' => [
            'base_uri' => 'https://api.example.com/',
            'timeout' => 10,
        ],
        'pay' => [
            'base_uri' => 'https://pay.example.com/',
            'timeout' => 5,
            'headers' => [
                'Authorization' => 'Bearer pay-token',
            ],
        ],
    ],
];
```

```php
$response = Httper::client('pay')->post('/orders', ['amount' => 100]);

$response = http('pay')->get('/orders/1');
```

## 请求方法

```php
$http = http();

$http->get($url, $query, $options);
$http->post($url, $body, $options);
$http->put($url, $body, $options);
$http->patch($url, $body, $options);
$http->delete($url, $query, $body, $options);
$http->head($url, $query, $options);
$http->options($url, $query, $options);
$http->request($method, $url, $data, $options);
$http->download($url, $filename, $options);
```

## 请求级选项

请求级 `$options` 会覆盖客户端配置，并支持以下特殊参数：

- `headers`：追加或覆盖请求头
- `query`：URL 查询参数（数组或字符串）
- `body`：请求体
- `json_request`：本次请求是否将数组请求体编码为 JSON

```php
$response = http_get('/users', [], [
    'timeout' => 5,
    'headers' => [
        'X-Request-Id' => 'abc123',
    ],
    'query' => [
        'page' => 1,
    ],
]);
```

## JSON 请求

```php
$response = http()->asJson()->post('/users', [
    'name' => '张三',
]);

// 或仅对单次请求开启
$response = http()->post('/users', ['name' => '张三'], [
    'json_request' => true,
]);
```

开启后会自动添加 `Content-Type: application/json`，并使用 `json_encode_flags` 编码数组请求体。

JSON 响应会根据响应头自动解码。需要原始字符串时关闭解码：

```php
$response = http()->post('/raw', [], [
    'json' => false,
]);
```

## 响应对象

```php
$response = http_get('/users');

$response->data;          // 解码后的响应数据
$response->raw;           // 原始响应字符串
$response->status;        // HTTP 状态码
$response->headers;       // 响应头数组
$response->cookies;       // 响应 Cookie 数组
$response->effectiveUrl;  // 最终请求 URL（跟随重定向后）
$response->method;        // 请求方法
$response->url;           // 请求 URL
$response->successful;    // 是否成功
$response->error;         // 是否失败
$response->errorCode;     // 错误码
$response->errorMessage;  // 错误信息
$response->curlError;     // 是否 cURL 错误
$response->totalTime;     // 总耗时（秒）
$response->attempts;      // 实际尝试次数
$response->requestHeaders;// 请求头

$response->ok();
$response->failed();
$response->isHttpError();
$response->json('data.list');
$response->header('Content-Type');
$response->cookie('session');
$response->toArray();
```

## 异常

开启 `throw` 配置后，最终响应失败会抛出 `HttperException`：

```php
$response = http()->post('/users', ['name' => '张三'], [
    'throw' => true,
]);
```

也可以在调用后主动抛出：

```php
use Youvision\ThinkHttper\Exception\HttperException;

try {
    $response = http_get('/users')->throw();
} catch (HttperException $e) {
    $response = $e->getResponse();
    $message = $e->getMessage();
}
```

## 重试

```php
$response = http_get('/users', [], [
    'retry' => 2,
    'retry_delay' => 100,
]);
```

- `retry`：最大重试次数，总尝试次数为 `retry + 1`
- `retry_delay`：每次重试前等待的毫秒数
- `retry_when`：自定义重试判定，签名 `function (Response $response, int $attempt): bool`

默认对网络错误、HTTP 5xx 和 429 重试。

## Cookie

```php
$response = http_get('/login', [], [
    'cookies' => [
        'session_id' => 'abc123',
    ],
]);

// 跨请求保持 Cookie
$response = http_get('/login', [], [
    'cookie_file' => runtime_path() . 'httper/cookies.txt',
    'cookie_jar' => runtime_path() . 'httper/cookies.txt',
]);
```

## 日志

```php
// config/httper.php
'log' => true,
'log_channel' => 'httper',
```

在 `config/log.php` 中配置对应通道：

```php
'channels' => [
    'httper' => [
        'type' => 'file',
        'path' => '',
        'level' => ['info', 'warning', 'error'],
    ],
],
```

日志记录请求方法、URL、状态码、耗时、重试次数和错误信息，不记录请求体、响应体和敏感请求头。

## 测试

```bash
composer install
vendor/bin/phpunit
```

测试会自动启动 PHP 内置 HTTP 服务器，覆盖请求、JSON、认证、超时、重试、Cookie、日志、下载、Facade、助手函数和配置映射等场景。

## 目录结构

```text
config/            配置文件
src/
  Exception/       异常类
  Facade/          Facade
  Httper.php       单客户端封装
  HttperManager.php 多客户端管理器
  Response.php     统一响应对象
  Service.php      ThinkPHP 服务
  helper.php       助手函数
tests/             测试
```