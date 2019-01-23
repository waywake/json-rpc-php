<?php

namespace JsonRpc\Providers;

use JsonRpc\Exception\RpcServerException;
use JsonRpc\Middleware\Security;
use JsonRpc\Middleware\TunnelMiddleware;
use JsonRpc\Server\JsonRpcDoc;
use JsonRpc\Server\JsonRpcServer;
use JsonRpc\Server\JsonRpcTool;
use Laravel\Lumen\Application;

class LumenServerServiceProvider extends BaseServiceProvider
{

    /**
     * @var Application
     */
    protected $app;

    /**
     * @var JsonRpcServer
     */
    protected $server;

    public function boot()
    {
        $this->app->middleware(TunnelMiddleware::class);
        $this->app->routeMiddleware(['rpc.security' => Security::class]);
        $this->app->router->group([
            'prefix' => 'rpc',
            'middleware' => 'rpc.security',
        ], function () {
            $config = config('rpc.server');
            $map = require_once $config['map'];
            $config['map'] = $map;
            if (!is_array($config)) {
                throw new RpcServerException("Application's Rpc Server Config Undefind", 500);
            }
            $callback = function () use ($config) {
                $server = new JsonRpcServer($config);
                return $server->handler();
            };

            $this->app->router->post('json-rpc-v2.json', $callback);
            $this->app->router->get('json-rpc-v2.json', $callback);

            if (function_exists('env') && env('APP_DEBUG')) {

                $tool = function () use ($config) {
                    $tool = new JsonRpcTool($config);
                    return $tool->render();
                };

                $this->app->router->get('tool.html', $tool);
                $this->app->router->post('tool.html', $tool);

                $this->app->router->get('doc.html', function () use ($config) {
                    $doc = new JsonRpcDoc($config);
                    return $doc->render();
                });

            }
        });
    }

    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        parent::register();
    }
}