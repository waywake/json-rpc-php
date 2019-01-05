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
//            'middleware' => 'rpc',
        ], function () {

            $callback = function () {
                $server = new JsonRpcServer();
                return $server->handler();
            };

            $this->app->router->post('rpc/gateway.json', $callback);
            $this->app->router->get('rpc/gateway.json', $callback);
            $this->app->router->get('rpc/doc.html', function () {
                $doc = new JsonRpcDoc(base_path('app/Rpc/'));
                return $doc->render();
            });
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