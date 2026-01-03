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
    public function register(): void
    {
        parent::register();

        $config = config('rpc');
        if (!is_array($config)) {
            throw new RpcServerException("Application's Rpc Client Config Undefind", 500);
        }

        $this->app->singleton('rpc', function () use ($config) {
            $client = new Client($config);
            $logger = $this->getLogger();
            if ($logger) {
                $client->setLogger($logger);
            }
            return $client;
        });

        foreach ($config['client'] as $k => $item) {
            $this->app->singleton('rpc.' . $k, function () use ($k, $config) {
                $client = (new Client($config))->endpoint($k);
                $logger = $this->getLogger();
                if ($logger) {
                    $client->setLogger($logger);
                }
                return $client;
            });
        }
    }

}
