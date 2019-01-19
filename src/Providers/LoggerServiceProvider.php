<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/18
 * Time: 1:36 PM
 */

namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use JsonRpc\Exception\RpcServerException;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class LoggerServiceProvider extends ServiceProvider
{

    public function register()
    {
        $this->app->configure('rpc');
        $config = config('rpc');
        if (!is_array($config)) {
            throw new RpcServerException("Application's Rpc Config Undefind", 500);
        }
        $this->app->singleton("rpc.logger", function () use ($config) {
            $default = [
                'app' => '***',
                'log_path' => "/logs/rpc_monitor_" . date("Ymd") . ".log",
                'log_formatter' => \JsonRpc\Logging\LogstashFormatter::class,
            ];
            $config = array_merge($default, $config);
            $stream = new StreamHandler($this->app->storagePath() . $config['log_path']);
            $stream->setFormatter(new $config['log_formatter']());
            $logger = new Logger('RPC.LOGGER');
            return $logger->pushHandler($stream);
        });
    }

}