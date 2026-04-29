<?php

namespace Tests\Unit;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Routing\Router;
use Illuminate\Support\Facades\Facade;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use JsonRpc\Client;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\Logging\LogstashFormatter;
use JsonRpc\Middleware\Security;
use JsonRpc\Middleware\TunnelMiddleware;
use JsonRpc\Providers\BaseServiceProvider;
use JsonRpc\Providers\ClientServiceProvider;
use JsonRpc\Providers\LaravelServerServiceProvider;
use Monolog\Logger;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Fixtures\ConfigMutatingFormatter;
use Tests\Fixtures\EchoRpc;

class ServiceProviderTest extends TestCase
{
    private array $temporaryFiles = [];

    protected function tearDown(): void
    {
        foreach ($this->temporaryFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testBaseProviderRegistersRpcLogger(): void
    {
        $app = $this->application([
            'rpc' => $this->rpcConfig(),
        ]);

        $provider = new BaseServiceProvider($app);
        $provider->register();

        $this->assertTrue($app->bound('rpc.logger'));
        $this->assertInstanceOf(Logger::class, $app->make('rpc.logger'));
        $this->assertSame($app->make('rpc.logger'), $provider->getLogger());
    }

    public function testClientProviderRegistersGenericAndEndpointClients(): void
    {
        $app = $this->application([
            'rpc' => $this->rpcConfig([
                'client' => [
                    'default' => [
                        'base_uri' => 'http://rpc.test',
                    ],
                ],
            ]),
        ]);

        (new ClientServiceProvider($app))->register();

        $this->assertInstanceOf(Client::class, $app->make('rpc'));
        $this->assertInstanceOf(Client::class, $app->make('rpc.default'));
        $this->assertSame($app->make('rpc.default'), $app->make('rpc.default'));
    }

    public function testClientProviderRejectsInvalidClientConfig(): void
    {
        $this->application([
            'rpc' => 'invalid',
        ]);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionMessage("Application's Rpc Client Config Undefined");

        $provider = new class(Container::getInstance()) extends ClientServiceProvider {
            protected function setupConfig(): void
            {
            }
        };

        $provider->register();
    }

    public function testClientProviderRejectsConfigThatBecomesInvalidAfterBaseRegistration(): void
    {
        $this->application([
            'rpc' => $this->rpcConfig([
                'log_formatter' => ConfigMutatingFormatter::class,
            ]),
        ]);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionMessage("Application's Rpc Client Config Undefined");

        (new ClientServiceProvider(Container::getInstance()))->register();
    }

    public function testLaravelServerProviderRegistersMiddlewareAndRoutes(): void
    {
        $mapFile = $this->temporaryMapFile([
            'math.add' => [EchoRpc::class, 'add'],
        ]);
        $app = $this->application([
            'app' => [
                'debug' => true,
                'name' => 'demo-app',
            ],
            'rpc' => $this->rpcConfig([
                'server' => [
                    'name' => 'demo',
                    'map' => $mapFile,
                ],
            ]),
        ]);
        $router = new Router(new Dispatcher($app), $app);
        $app->instance('router', $router);

        $provider = new LaravelServerServiceProvider($app);
        $provider->register();
        $provider->boot();

        $this->assertSame(Security::class, $router->getMiddleware()['rpc.security']);
        $this->assertSame(TunnelMiddleware::class, $router->getMiddleware()['rpc.tunnel']);

        $routes = [];
        foreach ($router->getRoutes() as $route) {
            $routes[$route->uri()][] = $route->methods();
        }

        $this->assertArrayHasKey('rpc/json-rpc-v2.json', $routes);
        $this->assertArrayHasKey('rpc/tool.html', $routes);
    }

    public function testLaravelServerProviderDispatchesJsonRpcRoute(): void
    {
        $mapFile = $this->temporaryMapFile([
            'math.add' => [EchoRpc::class, 'add'],
        ]);
        $app = $this->application([
            'app' => [
                'debug' => false,
                'name' => 'demo-app',
            ],
            'rpc' => $this->rpcConfig([
                'server' => [
                    'name' => 'demo',
                    'map' => $mapFile,
                ],
            ]),
        ]);
        $router = new Router(new Dispatcher($app), $app);
        $app->instance('router', $router);

        $provider = new LaravelServerServiceProvider($app);
        $provider->register();
        $provider->boot();

        $request = Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
            'REMOTE_ADDR' => '127.0.0.1',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => [6, 7],
            'id' => 14,
        ]));
        $app->instance('request', $request);

        $response = $router->dispatch($request);

        $this->assertSame(13, $response->getData(true)['result']);
    }

    public function testLaravelServerProviderDispatchesToolRouteWhenDebugIsEnabled(): void
    {
        $mapFile = $this->temporaryMapFile([
            'demo.documented' => [EchoRpc::class, 'documented'],
        ]);
        $app = $this->application([
            'app' => [
                'debug' => true,
                'name' => 'demo-app',
            ],
            'rpc' => $this->rpcConfig([
                'server' => [
                    'name' => 'demo',
                    'map' => $mapFile,
                ],
            ]),
        ]);
        $router = new Router(new Dispatcher($app), $app);
        $viewFactory = $this->viewFactory();
        $app->instance('router', $router);
        $app->instance('view', $viewFactory);
        $app->instance(ViewFactoryContract::class, $viewFactory);

        $provider = new LaravelServerServiceProvider($app);
        $provider->register();
        $provider->boot();

        $request = Request::create('/rpc/tool.html', 'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
        ]);
        $app->instance('request', $request);

        $response = $router->dispatch($request);

        $this->assertStringContainsString('Json Rpc Debug Tool', $response->getContent());
        $this->assertStringContainsString('demo.documented', $response->getContent());
    }

    public function testLaravelServerProviderDoesNotRegisterToolRoutesWhenDebugIsDisabled(): void
    {
        $mapFile = $this->temporaryMapFile([
            'math.add' => [EchoRpc::class, 'add'],
        ]);
        $app = $this->application([
            'app' => [
                'debug' => false,
                'name' => 'demo-app',
            ],
            'rpc' => $this->rpcConfig([
                'server' => [
                    'name' => 'demo',
                    'map' => $mapFile,
                ],
            ]),
        ]);
        $router = new Router(new Dispatcher($app), $app);
        $app->instance('router', $router);

        $provider = new LaravelServerServiceProvider($app);
        $provider->register();
        $provider->boot();

        $uris = array_map(static fn($route) => $route->uri(), iterator_to_array($router->getRoutes()));

        $this->assertContains('rpc/json-rpc-v2.json', $uris);
        $this->assertNotContains('rpc/tool.html', $uris);
    }

    public function testServerConfigLoadsMapFile(): void
    {
        $mapFile = $this->temporaryMapFile([
            'math.add' => [EchoRpc::class, 'add'],
        ]);
        $this->application([
            'rpc' => [
                'server' => [
                    'name' => 'demo',
                    'map' => $mapFile,
                ],
            ],
        ]);

        $config = $this->invokeServerConfig();

        $this->assertSame([
            'name' => 'demo',
            'map' => [
                'math.add' => [EchoRpc::class, 'add'],
            ],
        ], $config);
    }

    public function testServerConfigRejectsMissingServerConfig(): void
    {
        $this->application([
            'rpc' => [],
        ]);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionMessage("Application's Rpc Server Config Undefined");

        $this->invokeServerConfig();
    }

    public function testServerConfigRejectsMissingMapFile(): void
    {
        $this->application([
            'rpc' => [
                'server' => [
                    'name' => 'demo',
                    'map' => '/missing/rpc-method-map.php',
                ],
            ],
        ]);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionMessage("Application's Rpc Server Map Undefined");

        $this->invokeServerConfig();
    }

    public function testServerConfigRejectsInvalidMapFile(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'rpc-map-');
        file_put_contents($file, '<?php return "invalid";');
        $this->temporaryFiles[] = $file;

        $this->application([
            'rpc' => [
                'server' => [
                    'name' => 'demo',
                    'map' => $file,
                ],
            ],
        ]);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionMessage("Application's Rpc Server Map Invalid");

        $this->invokeServerConfig();
    }

    private function application(array $config = []): Application
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->detectEnvironment(static fn() => 'testing');
        $app->instance('config', new Repository(array_replace_recursive([
            'app' => [
                'debug' => false,
                'name' => 'demo-app',
            ],
        ], $config)));
        Container::setInstance($app);
        Facade::setFacadeApplication($app);

        return $app;
    }

    private function rpcConfig(array $overrides = []): array
    {
        return array_replace_recursive([
            'app' => 'demo-app',
            'log_path' => sys_get_temp_dir() . '/json-rpc-php-test.log',
            'log_formatter' => LogstashFormatter::class,
            'monitor' => [
                'enabled' => false,
            ],
            'server' => [
                'name' => 'demo',
                'map' => $this->temporaryMapFile([]),
            ],
            'client' => [
                'default' => [
                    'base_uri' => 'http://rpc.test',
                ],
            ],
        ], $overrides);
    }

    private function temporaryMapFile(array $map): string
    {
        $file = tempnam(sys_get_temp_dir(), 'rpc-map-');
        file_put_contents($file, '<?php return ' . var_export($map, true) . ';');
        $this->temporaryFiles[] = $file;

        return $file;
    }

    private function invokeServerConfig(): array
    {
        $method = new ReflectionMethod(LaravelServerServiceProvider::class, 'serverConfig');

        return $method->invoke(null);
    }

    private function viewFactory(): Factory
    {
        $filesystem = new Filesystem();
        $resolver = new EngineResolver();
        $cachePath = sys_get_temp_dir() . '/json-rpc-php-provider-blade-cache';
        $filesystem->ensureDirectoryExists($cachePath);

        $resolver->register('blade', static function () use ($filesystem, $cachePath) {
            return new CompilerEngine(new BladeCompiler($filesystem, $cachePath));
        });
        $resolver->register('php', static function () use ($filesystem) {
            return new \Illuminate\View\Engines\PhpEngine($filesystem);
        });

        return new Factory(
            $resolver,
            new FileViewFinder($filesystem, []),
            new Dispatcher(new Container())
        );
    }
}
