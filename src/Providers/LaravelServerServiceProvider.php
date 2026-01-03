<?php

namespace JsonRpc\Providers;

use Illuminate\Support\Facades\Route;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\Middleware\Security;
use JsonRpc\Middleware\TunnelMiddleware;
use JsonRpc\Server\JsonRpcDoc;
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
            ->group(function () {
                $config = config('rpc.server');
                $map = require_once $config['map'];
                $config['map'] = $map;
                if (!is_array($config)) {
                    throw new RpcServerException("Application's Rpc Server Config Undefind", 500);
                }
                $callback = function () use ($config) {
                    $server = new JsonRpcServer($config);
                    $logger = $this->getLogger();
                    if ($logger) {
                        $server->setLogger($logger);
                    }
                    return $server->handler();
                };

                Route::post('json-rpc-v2.json', $callback);
                Route::get('json-rpc-v2.json', $callback);

                if (function_exists('env') && env('APP_DEBUG')) {
                    $tool = function () use ($config) {
                        $tool = new JsonRpcTool($config);
                        return $tool->render();
                    };

                    Route::get('tool.html', $tool);
                    Route::post('tool.html', $tool);

                    Route::get('doc.html', function () use ($config) {
                        $doc = new JsonRpcDoc($config);
                        return $doc->render();
                    });
                }
            });
    }
}
