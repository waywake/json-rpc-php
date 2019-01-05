<?php

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Server\JsonRpcServer;
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
                $this->app->router->get('tools.html', function () {
                    $doc = new JsonRpcTool();
                    return $doc->render();
                });

//                $this->app->router->get('doc.html', function () {
//                $doc = new JsonRpcDoc(base_path('app/Rpc/'));
//                return $doc->render();
//            });

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