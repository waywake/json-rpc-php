<?php


namespace JsonRpc\Providers;

use Illuminate\Http\Request;
use Illuminate\Support\ServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class BaseServiceProvider extends ServiceProvider
{

    public function boot(){
        Request::setTrustedProxies([
            //pod network
            '172.20.0.0/16',
            //vpc
            '10.0.0.0/16',
            //local
            '127.0.0.1',
            //北京办公区
            '172.16.0.0/16',
            //aliyun slb
            '100.116.0/255.0/255',
        ], Request::HEADER_X_FORWARDED_ALL);
    }

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
            $stream = new StreamHandler($config['log_path']);
            $stream->setFormatter(new $config['log_formatter']());
            $logger = new Logger('RPC.LOGGER');
            return $logger->pushHandler($stream);
        });
    }

}