<?php

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Client;
use JsonRpc\Exception\RpcServerException;


class ClientServiceProvider extends ServiceProvider
{


    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->configure('rpc');
        $config = config('rpc');
        if (!is_array($config)) {
            throw new RpcServerException("Application's Rpc Config Undefind", 500);
        }
        $this->app->singleton('rpc', function () use ($config) {
            return new Client($config);
        });

        foreach ($config['client'] as $k => $item) {
            $this->app->singleton('rpc.' . $k, function () use ($k) {
                return app('rpc')->endpoint($k);
            });
        }
    }

}