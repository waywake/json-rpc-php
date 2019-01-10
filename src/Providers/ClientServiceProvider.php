<?php

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Client;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\StreamHandler;
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
        $dateFormat = "Y n j, g:i a";
        $output = "%datetime% > %level_name% > %message% %context% %extra%\n";
        $formatter = new LineFormatter($output, $dateFormat);
        $stream = new StreamHandler($this->app->storagePath()."/logs/rpc_monitor_".date("Ymd").".log");
        $stream->setFormatter($formatter);
        $logger = new Logger('RPC.LOGGER');
        $logger->pushHandler($stream);
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