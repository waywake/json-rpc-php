# waywake/json-rpc

一个面向 Laravel 12 的 JSON-RPC 2.0 服务端和客户端组件。它基于 HTTP 传输、Guzzle 发起调用、Laravel 路由和服务容器注册服务，并提供调试工具页、结构化 RPC 日志和可选监控写入。

## 环境要求

- PHP `^8.3`
- Laravel / Illuminate `^12.0`
- Guzzle `^7.8` 或 `^8.0`
- Monolog `^3.5`

## 安装

```bash
composer require waywake/json-rpc
```

如果项目尚未发布到默认 Composer 源，先添加 VCS 仓库：

```bash
composer config repositories.waywake-json-rpc vcs <git-repository-url>
composer require waywake/json-rpc
```

发布配置文件：

```bash
php artisan vendor:publish --tag=rpc-config
```

## 注册服务

在 Laravel 12 的 `config/app.php` 中注册服务提供者：

```php
'providers' => [
    JsonRpc\Providers\LaravelServerServiceProvider::class,
    JsonRpc\Providers\ClientServiceProvider::class,
],
```

注册后会自动挂载以下路由：

- `POST /rpc/json-rpc-v2.json`
- `GET /rpc/json-rpc-v2.json`
- `GET|POST /rpc/tool.html`，仅在 `config('app.debug')` 为 `true` 时可用

RPC 路由默认使用 `rpc.security` 中间件，只允许本地、内网和部分固定网段访问；`develop`、`local` 环境会直接放行。

## 配置

默认配置位于 `config/rpc.php`：

```php
return [
    'app' => env('APP_NAME'),
    'log_path' => storage_path('logs/rpc.log'),
    'log_formatter' => JsonRpc\Logging\LogstashFormatter::class,

    'monitor' => [
        'enabled' => env('RPC_MONITOR_SWITCH', false),
    ],

    'server' => [
        'name' => env('APP_NAME'),
        'map' => base_path('app/Rpc/method.php'),
    ],

    'client' => [
        'auth' => [
            'local' => true,
            'base_uri' => env('RPC_AUTH_URI', 'http://auth.dev.haowumc.com'),
        ],
    ],
];
```

关键字段说明：

- `app`：当前应用名，会写入 `X-Client-App` 请求头和日志。
- `log_path`：RPC 日志输出路径。
- `log_formatter`：Monolog formatter，默认输出 Logstash 风格 JSON。
- `monitor.enabled`：开启后，`TunnelMiddleware` 会尝试向 InfluxDB 写入状态指标；未安装 InfluxDB 客户端时会静默跳过。
- `server.map`：服务端方法映射文件。
- `client.*.base_uri`：客户端调用的远端服务地址。

## 服务端

创建方法映射文件，例如 `app/Rpc/method.php`：

```php
<?php

return [
    'user.info' => [\App\Rpc\User\RpcUser::class, 'getUserInfo'],
    'order.info' => [\App\Rpc\Order\RpcOrder::class, 'getOrderInfo'],
];
```

创建 RPC 方法类：

```php
<?php

namespace App\Rpc\User;

use JsonRpc\JsonRpc;
use JsonRpc\Server\JsonRpcMethod;

class RpcUser extends JsonRpcMethod
{
    /**
     * @title 获取用户信息
     * @param int uid 用户ID 空 是
     * @return int code 状态码
     * @return array data 用户信息
     */
    public function getUserInfo(int $uid): array
    {
        if ($uid <= 0) {
            return $this->error(JsonRpc::Rpc_Error_Invalid_Params, 'Invalid uid');
        }

        return $this->response([
            'uid' => $uid,
            'name' => 'demo',
        ]);
    }
}
```

注意事项：

- RPC 方法类需要继承 `JsonRpc\Server\JsonRpcMethod`。
- 不要在方法类中重新定义构造函数，基类构造函数是 `final`。
- 成功响应使用 `$this->response($result)`。
- 错误响应使用 `$this->error($code, $messageOrData)`。
- 必填参数数量不足会返回 `-32602 Invalid params`。
- `params` 支持位置参数数组；关联数组会在键名匹配方法参数名时按声明顺序调用，否则退回为位置参数。

## 请求格式

POST 请求体：

```json
{
  "jsonrpc": "2.0",
  "method": "user.info",
  "params": [123],
  "id": 1
}
```

GET 调试请求：

```text
/rpc/json-rpc-v2.json?method=user.info&params=[123]&id=1
```

成功响应：

```json
{
  "jsonrpc": "2.0",
  "result": {
    "uid": 123,
    "name": "demo"
  },
  "id": 1
}
```

错误响应：

```json
{
  "jsonrpc": "2.0",
  "error": {
    "code": -32601,
    "message": "Method not found"
  },
  "id": 1
}
```

## 客户端

服务提供者会为 `config('rpc.client')` 中的每个 endpoint 注册一个容器单例：

```php
$result = app('rpc.auth')->call('user.info', [123]);
```

也可以使用通用客户端手动选择 endpoint：

```php
$client = app('rpc')->endpoint('auth');

$result = $client->call('user.info', [123], [
    'timeout' => 3,
]);
```

客户端会向 `/rpc/json-rpc-v2.json?app=<当前应用名>` 发送 POST 请求，并携带：

- `X-Client-App`
- `X-Request-Id`

远端返回 JSON-RPC `error` 时，客户端会抛出 `JsonRpc\Exception\RpcServerException`。

## 工具页

在 `APP_DEBUG=true` 时访问：

```text
http://<host>/rpc/tool.html
```

工具页会读取 `server.map` 中注册的方法，解析方法注释中的 `@title`、`@param`、`@return`、`@code`，并提供简单的调用表单。

支持的注释格式：

```php
/**
 * @title 用户登录
 * @param string username 账号 空 是
 * @param string password 密码 空 是
 * @return int code 状态码
 * @return string msg 提示信息
 * @code 0 成功
 * @code -32602 参数错误
 */
```

## 错误码

错误码定义在 `JsonRpc\JsonRpc`：

| 常量 | 值 | 含义 |
| --- | ---: | --- |
| `Rpc_Error_Parse_Error` | `-32700` | JSON 解析错误 |
| `Rpc_Error_Invalid_Request` | `-32600` | 请求无效 |
| `Rpc_Error_NOT_FOUND` | `-32601` | 方法不存在 |
| `Rpc_Error_Invalid_Params` | `-32602` | 参数无效 |
| `Rpc_Error_Internal_Error` | `-32603` | 内部错误 |
| `Rpc_Error_System_Error` | `-32400` | 业务或系统错误 |
| `Rpc_Success` | `0` | 成功 |

## 日志与监控

`BaseServiceProvider` 会注册 `rpc.logger`，默认写入 `config('rpc.log_path')`。`LogstashFormatter` 会输出包含时间、主机、应用名、环境、调用方应用和请求 ID 的 JSON 日志。

如果启用 `rpc.monitor.enabled` 并且项目安装了 `influxdb/influxdb-php`，`TunnelMiddleware` 会在响应结束时写入 RPC 状态指标。该依赖是可选项。

## 本地开发

```bash
composer install
composer dump-autoload
composer test
composer test:lint
composer test:coverage
composer validate --no-check-publish
```

测试套件使用 PHPUnit，配置在 `phpunit.xml`，测试文件位于 `tests/Unit`，夹具位于 `tests/Fixtures`。当前测试规模为 59 个测试、121 个断言。当前覆盖重点包括：

- JSON-RPC 服务端 POST / GET 分发、命名参数归一化、缺失参数、未知方法和 JSON 解析错误。
- JSON-RPC 方法基类的成功与错误响应格式。
- Guzzle 客户端请求载荷、请求头、结果解析、远端错误、空响应和 HTTP 500 包装异常。
- `rpc.security` 中间件的本地环境、内网和公网访问判断。
- `TunnelMiddleware` 的普通响应、监控开关、环境判断和无 InfluxDB 客户端分支。
- Provider 的日志注册、客户端容器绑定、服务端中间件和路由注册。
- Logstash formatter 输出结构。
- 工具页方法列表、endpoint 和 DocBlock 文档解析。

启用 Xdebug 或 PCOV 后可生成覆盖率报告。覆盖率 source 排除了 `src/views` 下的 Blade 模板，模板通过工具页渲染测试做行为验证：

```bash
composer test:coverage
```

当前覆盖率快照：

```text
Classes: 100.00% (11/11)
Methods: 100.00% (44/44)
Lines:   100.00% (378/378)
```

修改 PHP 代码后建议至少运行：

```bash
composer test
composer test:lint
composer test:coverage
composer validate --no-check-publish
```

`tests/publish.php` 和 `tests/sub.php` 仍是手动调用示例脚本，不属于 PHPUnit 自动化测试套件。
