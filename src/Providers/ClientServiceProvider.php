<?php

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Client;
use Monolog\Logger;

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

        $config = config('rpc.client');
        $logger = new Logger('rpc-client-logger');
        $logger->pushHandler($this->app->storagePath()."/logs/rpc_client_".date("Ymd").".log");
        $this->app->singleton('rpc', function () use ($config, $logger) {
            return new Client($config, $logger);
        });

        foreach ($config as $k => $item) {
            $this->app->singleton('rpc.' . $k, function () use ($k) {
                return app('rpc')->endpoint($k);
            });
        }
    }
}