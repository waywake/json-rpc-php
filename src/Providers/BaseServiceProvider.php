<?php


namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Exception\RpcServerException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class BaseServiceProvider extends ServiceProvider
{


    protected function setupConfig()
    {
        $source = realpath(__DIR__ . '/../../config/rpc.php');

        if ($this->app instanceof LaravelApplication && $this->app->runningInConsole()) {
            $this->publishes([$source => config_path('rpc.php')], 'rpc');
        } elseif ($this->app instanceof LumenApplication) {
            $this->app->configure('rpc');
        }

        $this->mergeConfigFrom($source, 'rpc');
    }

    public function register()
    {
        $this->setupConfig();
        $this->app->singleton("rpc.logger", function () {
            $config = config('rpc');
            $stream = new StreamHandler($this->app->storagePath() . $config['log_path']);
            $stream->setFormatter(new $config['log_formatter']());
            $logger = new Logger('RPC.LOGGER');
            return $logger->pushHandler($stream);
        });
    }

}