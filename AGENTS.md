# AGENTS.md

本文件给后续在此仓库工作的自动化代理或协作者使用。请先阅读当前代码，再动手修改。

## 仓库概览

- 包名：`waywake/json-rpc`
- 类型：Composer library
- 运行环境：PHP `^8.3`，Laravel / Illuminate `^12.0`
- 命名空间：`JsonRpc\` -> `src/`
- 主要入口：
  - `src/Providers/LaravelServerServiceProvider.php`：注册 RPC 服务端路由和调试工具页。
  - `src/Providers/ClientServiceProvider.php`：注册 `rpc` 和 `rpc.<endpoint>` 客户端单例。
  - `src/Server/JsonRpcServer.php`：解析 JSON-RPC 请求并分发到映射方法。
  - `src/Server/JsonRpcMethod.php`：服务端方法基类，提供 `response()` 和 `error()`。
  - `src/Client.php`：Guzzle 客户端封装。
  - `config/rpc.php`：默认配置。

## 常用命令

```bash
composer install
composer dump-autoload
composer test
composer test:lint
composer validate --no-check-publish
```

测试配置在 `phpunit.xml`。自动化测试位于 `tests/Unit`，测试夹具位于 `tests/Fixtures`。当前测试规模为 59 个测试、121 个断言；启用 Xdebug 或 PCOV 后可用 `XDEBUG_MODE=coverage vendor/bin/phpunit --coverage-text` 查看覆盖率。覆盖率 source 排除了 `src/views` 下的 Blade 模板，模板通过工具页渲染测试做行为验证。`tests/publish.php` 和 `tests/sub.php` 是手动调用示例，不属于 PHPUnit suite。

## 修改约定

- 保持 PHP 8.4 兼容，优先使用现有 Laravel 12 / Illuminate API。
- 保持 PSR-4 自动加载结构，新增类放到 `src/` 下对应命名空间。
- 不要恢复已删除的旧文档页或旧依赖，例如 `JsonRpcDoc`、`src/views/doc.blade.php`、`itxq/api-doc-php`。
- 不要重新引入旧版 Lumen provider；当前服务端入口是 `LaravelServerServiceProvider`。
- 服务端方法映射必须保持形如 `method.name => [ClassName::class, 'method']`。
- RPC 方法类继承 `JsonRpc\Server\JsonRpcMethod`，不要定义构造函数，因为基类构造函数是 `final`。
- 客户端 endpoint 配置会直接与 Guzzle 默认配置合并，新增配置项时确认 Guzzle 能接受。
- 修改中间件或路由时，注意 `rpc.security` 默认限制来源 IP，调试工具页只在 `config('app.debug')` 为真时开放。
- 修改日志格式时，确认 `LogstashFormatter::format()` 仍返回单行 JSON 字符串。

## 文档约定

- README 应反映当前代码行为，而不是旧包名或历史实现。
- 工具页支持从方法注释中解析 `@title`、`@param`、`@return`、`@code`。
- 示例代码优先展示 Laravel 12 和当前 `waywake/json-rpc` 包名。
- 如果新增配置项、路由、错误码、容器绑定或测试命令，同步更新 `README.md`。

## 协作注意事项

- 工作区可能已有用户未提交改动。不要执行 `git reset --hard`、`git checkout -- <file>` 等破坏性命令，除非用户明确要求。
- 修改前先检查 `git status --short`，避免覆盖无关改动。
- 保持变更范围小而清晰；不要顺手重构无关文件。
- 完成代码修改后至少运行 `composer test`、`composer test:lint` 和 `composer validate --no-check-publish`。只改文档时可运行 `composer validate --no-check-publish` 作为基础健康检查。
- 如需提交代码，commit message 必须遵循 Conventional Commits，例如 docs: update project documentation、fix: handle expired token。
