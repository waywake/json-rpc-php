<?php

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Client;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\Server\JsonRpcServer;


class ClientServiceProvider extends BaseServiceProvider
{
    /**
     * @throws RpcServerException
     */
    public function register()
    {
        parent::register();
        $this->app->configure('rpc');
        $config = config('rpc');
        if (!is_array($config)) {
            throw new RpcServerException("Application's Rpc Client Config Undefind", 500);
        }
	    $client = new Client($config);
	    $this->app->singleton('rpc', function () use ($client) {
            return $client;
        });

        foreach ($config['client'] as $k => $item) {
            $this->app->singleton('rpc.' . $k, function () use ($k, $client) {
                return $client->endpoint($k);
            });
        }
    }

}