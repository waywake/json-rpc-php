<?php

namespace JsonRpc\Providers;

use Illuminate\Support\Facades\Route;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\Middleware\Security;
use JsonRpc\Middleware\TunnelMiddleware;
use JsonRpc\Server\JsonRpcServer;
use JsonRpc\Server\JsonRpcTool;

class LaravelServerServiceProvider extends BaseServiceProvider
{
    /**
     * @var JsonRpcServer|null
     */
    protected ?JsonRpcServer $server = null;

    public function boot(): void
    {
        parent::boot();

        $this->registerMiddleware();
        $this->registerRoutes();
    }

    protected function registerMiddleware(): void
    {
        $router = $this->app['router'];
        if (method_exists($router, 'aliasMiddleware')) {
            $router->aliasMiddleware('rpc.security', Security::class);
            $router->aliasMiddleware('rpc.tunnel', TunnelMiddleware::class);
        }
    }

    protected function registerRoutes(): void
    {
        Route::prefix('rpc')
            ->middleware(['rpc.security'])
            ->group(static function () {
                $callback = static function () {
                    $config = self::serverConfig();
                    $server = new JsonRpcServer($config);
                    $logger = app()->bound('rpc.logger') ? app('rpc.logger') : null;
                    if ($logger) {
                        $server->setLogger($logger);
                    }
                    return $server->handler();
                };

                Route::post('json-rpc-v2.json', $callback);
                Route::get('json-rpc-v2.json', $callback);

                if ((bool) config('app.debug', false)) {
                    $tool = static function () {
                        $config = self::serverConfig();
                        $tool = new JsonRpcTool($config);
                        return $tool->render();
                    };

                    Route::get('tool.html', $tool);
                    Route::post('tool.html', $tool);
                }
            });
    }

    /**
     * @throws RpcServerException
     */
    protected static function serverConfig(): array
    {
        $config = config('rpc.server');
        if (!is_array($config)) {
            throw new RpcServerException("Application's Rpc Server Config Undefined", 500);
        }

        $mapPath = $config['map'] ?? null;
        if (!is_string($mapPath) || !is_file($mapPath)) {
            throw new RpcServerException("Application's Rpc Server Map Undefined", 500);
        }

        $map = require $mapPath;
        if (!is_array($map)) {
            throw new RpcServerException("Application's Rpc Server Map Invalid", 500);
        }

        $config['map'] = $map;
        return $config;
    }
}
