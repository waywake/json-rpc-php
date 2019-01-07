<?php

namespace JsonRpc\Providers;

use App\Http\Middleware\JsonRpc;
use Illuminate\Support\ServiceProvider;
use JsonRpc\Server\JsonRpcDoc;
use JsonRpc\Server\JsonRpcServer;
use JsonRpc\Server\JsonRpcTool;
use Laravel\Lumen\Application;

class LumenServerServiceProvider extends ServiceProvider
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
        $this->app->router->group([
            'prefix' => 'rpc'
//            'middleware' => 'rpc',
        ], function () {

            $this->app->configure('rpc');
            $config = config('rpc.server');

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
    }
}