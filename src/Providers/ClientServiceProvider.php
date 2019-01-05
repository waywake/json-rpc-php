<?php

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Client;

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
        $this->app->singleton('rpc', function () use ($config) {
            return new Client($config);
        });

        foreach ($config as $k => $item) {
            $this->app->singleton('rpc.' . $k, function () use ($k) {
                app('rpc')->endpoint($k);
            });
        }
    }
}