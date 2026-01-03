<?php


namespace JsonRpc\Providers;

use Illuminate\Support\ServiceProvider;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Monolog\Level;
use Psr\Log\LoggerInterface;

class BaseServiceProvider extends ServiceProvider
{

    protected ?LoggerInterface $logger = null;

    public function boot(): void
    {
        // Trusted proxies configuration removed for Lumen 11
        // Users must configure TrustProxies middleware in bootstrap/app.php
    }

    protected function setupConfig(): void
    {
        $source = realpath(__DIR__ . '/../../config/rpc.php');
        if (method_exists($this->app, 'configure')) {
            $this->app->configure('rpc');
        }
        $this->mergeConfigFrom($source, 'rpc');

        if (method_exists($this->app, 'configPath')) {
            $this->publishes([
                $source => $this->app->configPath('rpc.php'),
            ], 'rpc-config');
        }
    }

    public function register(): void
    {
        $this->setupConfig();

        $config = config('rpc');

        $this->logger = new Logger('RPC.LOGGER');
        $stream = new StreamHandler($config['log_path'], Level::Debug);
        $stream->setFormatter(new $config['log_formatter']());
        $this->logger->pushHandler($stream);
    }

    public function getLogger(): ?LoggerInterface
    {
        return $this->logger;
    }

}
