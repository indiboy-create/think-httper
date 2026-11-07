<?php

/**
 * think-httper 配置文件
 *
 * 配置合并顺序：Httper 内置默认值 < defaults < clients.{当前客户端} < 请求级 $options。
 * 请求级 $options 支持覆盖本文件中的所有同名配置项，并额外支持：
 *   - query：仅用于 GET/HEAD/OPTIONS/DELETE，作为 URL 查询参数（数组或字符串）。
 *   - body：仅用于 POST/PUT/PATCH/DELETE，作为请求体（数组或字符串）。
 *   - json_request：本次请求是否将数组请求体编码为 JSON。
 *   - headers：本次请求追加或覆盖的请求头。
 */
return [
    /*
     * 默认客户端名称。
     * 类型：string
     * 默认：default
     * 生效：Facade、助手函数或依赖注入未指定客户端时，使用该名称对应的 clients 配置。
     */
    'default' => 'default',

    /*
     * 所有客户端共享的默认配置。
     * 类型：array
     * 说明：clients 中的每个客户端会与 defaults 深度合并，客户端配置优先。
     */
    'defaults' => [
        /*
         * 基础 URL。
         * 类型：string
         * 默认：空字符串
         * 生效：相对路径会与 base_uri 拼接；以 / 开头的路径会基于 base_uri 的协议和域名拼接；
         *       http:// 或 https:// 开头的绝对 URL 不受影响。
         * 示例：https://api.example.com/v1
         */
        'base_uri' => '',

        /*
         * 请求总超时时间，单位：秒。
         * 类型：int|float|null
         * 默认：30
         * 生效：整数调用 Curl::setOpt(CURLOPT_TIMEOUT)；小数调用 CURLOPT_TIMEOUT_MS，精度到毫秒。
         *       null 表示不覆盖 php-curl-class 的默认值（30 秒）；0 表示不限制超时。
         */
        'timeout' => 30,

        /*
         * 连接超时时间，单位：秒。
         * 类型：int|float|null
         * 默认：10
         * 生效：整数调用 CURLOPT_CONNECTTIMEOUT；小数调用 CURLOPT_CONNECTTIMEOUT_MS。
         *       null 表示不覆盖 curl 默认值；0 表示不限制连接超时。
         */
        'connect_timeout' => 10,

        /*
         * 失败重试次数（不含首次请求）。
         * 类型：int
         * 默认：0
         * 生效：由 Httper 自身执行重试循环，总尝试次数 = retry + 1。
         *       默认对网络错误、HTTP 5xx、429 重试；可通过 retry_when 自定义。
         */
        'retry' => 0,

        /*
         * 每次重试前的等待时间，单位：毫秒。
         * 类型：int
         * 默认：0
         * 生效：重试前调用 usleep()，0 表示立即重试。
         */
        'retry_delay' => 0,

        /*
         * 自定义重试判定回调。
         * 类型：callable|null
         * 默认：null
         * 生效：签名 function (Response $response, int $attempt): bool。
         *       返回 true 时继续重试；返回 false 时停止。
         */
        'retry_when' => null,

        /*
         * 默认请求头。
         * 类型：array
         * 默认：[]
         * 生效：调用 Curl::setHeaders()。支持关联数组或 "Name: value" 字符串列表。
         * 示例：['Accept' => 'application/json', 'X-Request-Id' => 'xxx']
         */
        'headers' => [],

        /*
         * User-Agent。
         * 类型：string
         * 默认：空字符串（不覆盖 curl / php-curl-class 默认值）
         * 生效：非空时调用 Curl::setUserAgent()。
         */
        'user_agent' => '',

        /*
         * Referer。
         * 类型：string
         * 默认：空字符串
         * 生效：非空时调用 Curl::setReferrer()。
         */
        'referer' => '',

        /*
         * 请求 Cookie。
         * 类型：array
         * 默认：[]
         * 生效：调用 Curl::setCookies()，数组键为 Cookie 名称，值为 Cookie 值。
         * 示例：['session_id' => 'abc123']
         */
        'cookies' => [],

        /*
         * Cookie 读取文件。
         * 类型：string
         * 默认：空字符串
         * 生效：非空时调用 Curl::setCookieFile()，从该文件读取 Cookie。
         */
        'cookie_file' => '',

        /*
         * Cookie 保存文件。
         * 类型：string
         * 默认：空字符串
         * 生效：非空时调用 Curl::setCookieJar()，响应 Cookie 会写入该文件，
         *       跨请求、跨客户端实例共享登录态时非常有用。
         */
        'cookie_jar' => '',

        /*
         * 认证配置。
         * 类型：array
         * 生效：
         *   - type=basic：调用 Curl::setBasicAuthentication()；
         *   - type=digest：调用 Curl::setDigestAuthentication()；
         *   - type=bearer：设置 Authorization: Bearer {token} 请求头，并设置 CURLOPT_XOAUTH2_BEARER；
         *   - type 为空：不设置认证。
         */
        'auth' => [
            // 认证类型：basic、digest、bearer，或留空。
            'type' => '',
            // basic / digest 认证用户名。
            'username' => '',
            // basic / digest 认证密码。
            'password' => '',
            // bearer 认证令牌。
            'token' => '',
        ],

        /*
         * 代理配置。
         * 类型：array|string
         * 生效：
         *   - 字符串：调用 Curl::setProxy()，可写 http://user:pass@host:port；
         *   - 数组：host 非空时调用 Curl::setProxy()；
         *     type 非空时调用 Curl::setProxyType()；
         *     tunnel 调用 Curl::setProxyTunnel()；
         *     auth 调用 Curl::setProxyAuth()。
         */
        'proxy' => [
            // 代理地址，例如 127.0.0.1 或 http://127.0.0.1。
            'host' => '',
            // 代理端口，null 表示使用 host 中的端口或不设置。
            'port' => null,
            // 代理用户名，空字符串表示不需要认证。
            'username' => '',
            // 代理密码。
            'password' => '',
            // 代理类型：http、https、socks4、socks4a、socks5、socks5_hostname，或 CURLPROXY_* 常量。
            'type' => null,
            // 是否通过 HTTP 代理建立隧道。
            'tunnel' => false,
            // 代理认证方式，CURLOPT_PROXYAUTH 的值，例如 CURLAUTH_BASIC。
            'auth' => null,
        ],

        /*
         * SSL/TLS 配置。
         * 类型：array
         * 生效：直接映射为 curl 的 SSL 选项。
         */
        'ssl' => [
            // 是否验证对端证书，对应 CURLOPT_SSL_VERIFYPEER。
            'verify_peer' => true,
            // 是否验证证书域名，对应 CURLOPT_SSL_VERIFYHOST，常用值 2。
            'verify_host' => 2,
            // 客户端证书路径，对应 CURLOPT_SSLCERT。
            'cert' => '',
            // 客户端证书密码，对应 CURLOPT_SSLCERTPASSWD。
            'cert_password' => '',
            // 客户端私钥路径，对应 CURLOPT_SSLKEY。
            'key' => '',
        ],

        /*
         * 额外的 curl 原生选项。
         * 类型：array
         * 默认：[]
         * 生效：最后调用 Curl::setOpts()，优先级最高，可覆盖上面的同名选项。
         * 示例：[CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5]
         */
        'options' => [],

        /*
         * JSON 响应解码开关。
         * 类型：bool|callable
         * 默认：true
         * 生效：
         *   - true：保持 php-curl-class 默认行为，根据响应 Content-Type 自动解码 JSON；
         *   - false：调用 Curl::setJsonDecoder(false)，response 保留原始字符串；
         *   - callable：调用 Curl::setJsonDecoder(callable)，使用自定义解码函数。
         */
        'json' => true,

        /*
         * XML 响应解码开关。
         * 类型：bool|callable
         * 默认：true
         * 生效：与 json 配置相同，对应 Curl::setXmlDecoder()。
         */
        'xml' => true,

        /*
         * 是否将数组请求体编码为 JSON。
         * 类型：bool
         * 默认：false
         * 生效：开启后自动添加 Content-Type: application/json，
         *       并对 POST/PUT/PATCH/DELETE 的数组请求体调用 json_encode()。
         *       已设置 Content-Type 时保留用户设置。
         */
        'json_request' => false,

        /*
         * JSON 请求体编码选项。
         * 类型：int
         * 默认：JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
         * 生效：仅在 json_request 为 true 且请求体为数组时传给 json_encode()。
         */
        'json_encode_flags' => JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,

        /*
         * 是否记录 HTTP 请求日志。
         * 类型：bool
         * 默认：false
         * 生效：开启后通过 ThinkPHP 日志组件记录请求方法、URL、状态码、耗时、重试次数和错误信息，
         *       不记录请求体、响应体和敏感请求头。
         */
        'log' => false,

        /*
         * 日志通道名称。
         * 类型：string
         * 默认：httper
         * 生效：调用 ThinkPHP Log::channel() 时使用；通道需在 config/log.php 中配置。
         */
        'log_channel' => 'httper',

        /*
         * 请求失败时是否抛出异常。
         * 类型：bool
         * 默认：false
         * 生效：最终响应失败（网络错误或 HTTP 4xx/5xx）时抛出 HttperException，
         *       异常对象可通过 getResponse() 获取完整 Response。
         */
        'throw' => false,

        /*
         * 请求发送前回调。
         * 类型：callable|null
         * 默认：null
         * 生效：每次尝试请求前调用，签名 function (Curl $curl, string $method, string $url, mixed $data, array $config): void。
         */
        'before' => null,

        /*
         * 响应完成后回调。
         * 类型：callable|null
         * 默认：null
         * 生效：每次尝试收到响应后调用，签名 function (Response $response, Curl $curl, array $config): void。
         *       回调执行时 Curl 实例尚未关闭。
         */
        'after' => null,
    ],

    /*
     * 客户端列表。
     * 类型：array
     * 说明：每个键为客户端名称，值为覆盖 defaults 的配置数组。
     *       可通过 Facade::client('api')、http('api') 或依赖注入 HttperManager 使用。
     * 示例：
     * 'clients' => [
     *     'default' => [],
     *     'api' => [
     *         'base_uri' => 'https://api.example.com/',
     *         'timeout' => 10,
     *         'headers' => ['Authorization' => 'Bearer token'],
     *     ],
     * ],
     */
    'clients' => [
        'default' => [],
    ],
];