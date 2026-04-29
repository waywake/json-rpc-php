<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Contracts\View\Factory as ViewFactoryContract;
use Illuminate\Events\Dispatcher;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Request;
use Illuminate\View\Compilers\BladeCompiler;
use Illuminate\View\Engines\CompilerEngine;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory;
use Illuminate\View\FileViewFinder;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\Server\JsonRpcTool;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;
use Tests\Fixtures\EchoRpc;

class JsonRpcToolTest extends TestCase
{
    private array $temporaryDirectories = [];

    protected function tearDown(): void
    {
        foreach (array_reverse($this->temporaryDirectories) as $directory) {
            if (is_dir($directory)) {
                (new Filesystem())->deleteDirectory($directory);
            }
        }

        Container::setInstance(null);
        parent::tearDown();
    }

    public function testItReturnsConfiguredMethods(): void
    {
        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'demo.documented' => [EchoRpc::class, 'documented'],
            ],
        ]);

        $this->assertSame([
            'demo.documented' => [EchoRpc::class, 'documented'],
        ], $tool->getMethods());
    }

    public function testItBuildsDocDataFromMethodDocblocks(): void
    {
        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'demo.documented' => [EchoRpc::class, 'documented'],
            ],
        ]);

        $method = new ReflectionMethod($tool, 'getDocData');

        $data = $method->invoke($tool);

        $this->assertSame('Documented method', $data['demo.documented']['title']);
        $this->assertSame('demo.documented', $data['demo.documented']['method']);
        $this->assertSame([
            [
                'param_type' => 'string',
                'param_name' => 'name',
                'param_title' => '用户名',
                'param_default' => '空',
                'param_require' => '是',
            ],
        ], $data['demo.documented']['param']);
        $this->assertSame([
            [
                'return_type' => 'string',
                'return_name' => 'message',
                'return_title' => '提示信息',
            ],
        ], $data['demo.documented']['return']);
        $this->assertSame([
            [
                'code' => '0',
                'content' => '成功',
            ],
        ], $data['demo.documented']['code']);
    }

    public function testItBuildsFallbackDocDataFromReflectionParameters(): void
    {
        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'demo.join' => [EchoRpc::class, 'join'],
            ],
        ]);

        $method = new ReflectionMethod($tool, 'getDocData');

        $data = $method->invoke($tool);

        $this->assertSame([
            [
                'param_name' => 'first',
                'param_type' => 'string',
                'param_title' => '',
                'param_default' => '',
                'param_require' => '是',
            ],
            [
                'param_name' => 'second',
                'param_type' => 'string',
                'param_title' => '',
                'param_default' => 'fallback',
                'param_require' => '否',
            ],
        ], $data['demo.join']['param']);
    }

    public function testItSkipsInvalidMapEntriesAndMissingMethods(): void
    {
        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'invalid.entry' => 'not-array',
                'missing.method' => [EchoRpc::class, 'missing'],
            ],
        ]);

        $method = new ReflectionMethod($tool, 'getDocData');

        $data = $method->invoke($tool);

        $this->assertArrayNotHasKey('invalid.entry', $data);
        $this->assertSame('missing.method', $data['missing.method']['title']);
        $this->assertSame([], $data['missing.method']['param']);
    }

    public function testItBuildsEndpointFromCurrentRequest(): void
    {
        $container = new Container();
        $container->instance('request', Request::create('https://rpc.example.test/rpc/tool.html'));
        Container::setInstance($container);

        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [],
        ]);

        $this->assertSame('https://rpc.example.test/rpc/json-rpc-v2.json', $tool->getEndpoint());
    }

    public function testItRendersBundledBladeToolForGetRequests(): void
    {
        $this->bindToolApplication(Request::create('https://rpc.example.test/rpc/tool.html', 'GET', [
            'method' => 'demo.documented',
            'params' => '["Ada"]',
        ]));

        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'demo.documented' => [EchoRpc::class, 'documented'],
            ],
        ]);

        $html = $tool->render()->render();

        $this->assertStringContainsString('Json Rpc Debug Tool', $html);
        $this->assertStringContainsString('demo.documented', $html);
        $this->assertStringContainsString('https://rpc.example.test/rpc/json-rpc-v2.json', $html);
    }

    public function testItRendersPostResults(): void
    {
        $container = $this->bindToolApplication(Request::create('https://rpc.example.test/rpc/tool.html', 'POST', [
            'method' => 'demo.documented',
            'params' => '["Ada"]',
        ]));
        $container->instance('rpc.demo', new class {
            public function call(string $method, array $params): array
            {
                return [
                    'method' => $method,
                    'params' => $params,
                ];
            }
        });

        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'demo.documented' => [EchoRpc::class, 'documented'],
            ],
        ]);

        $html = $tool->render()->render();

        $this->assertStringContainsString('Result:', $html);
        $this->assertStringContainsString('demo.documented', $html);
        $this->assertStringContainsString('Ada', $html);
    }

    public function testItRendersPostErrors(): void
    {
        $container = $this->bindToolApplication(Request::create('https://rpc.example.test/rpc/tool.html', 'POST', [
            'method' => 'demo.documented',
            'params' => '["Ada"]',
        ]));
        $container->instance('rpc.demo', new class {
            public function call(): never
            {
                throw new RpcServerException('bad rpc', 500);
            }
        });

        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [
                'demo.documented' => [EchoRpc::class, 'documented'],
            ],
        ]);

        $html = $tool->render()->render();

        $this->assertStringContainsString('bad rpc', $html);
        $this->assertStringContainsString('500', $html);
    }

    public function testItRendersNamedToolViewWhenApplicationOverridesIt(): void
    {
        $viewDirectory = sys_get_temp_dir() . '/json-rpc-php-views-' . uniqid('', true);
        (new Filesystem())->ensureDirectoryExists($viewDirectory);
        file_put_contents($viewDirectory . '/tool.blade.php', 'custom tool {{ $endpoint }}');
        $this->temporaryDirectories[] = $viewDirectory;

        $container = new Container();
        $request = Request::create('https://rpc.example.test/rpc/tool.html');
        $viewFactory = $this->viewFactory([$viewDirectory]);
        $container->instance('request', $request);
        $container->instance('view', $viewFactory);
        $container->instance(ViewFactoryContract::class, $viewFactory);
        Container::setInstance($container);

        $tool = new JsonRpcTool([
            'name' => 'demo',
            'map' => [],
        ]);

        $this->assertSame(
            'custom tool https://rpc.example.test/rpc/json-rpc-v2.json',
            trim($tool->render()->render())
        );
    }

    private function bindToolApplication(Request $request): Container
    {
        $container = new Container();
        $container->instance('request', $request);
        $viewFactory = $this->viewFactory();
        $container->instance('view', $viewFactory);
        $container->instance(ViewFactoryContract::class, $viewFactory);
        Container::setInstance($container);

        return $container;
    }

    private function viewFactory(array $locations = []): Factory
    {
        $filesystem = new Filesystem();
        $resolver = new EngineResolver();
        $cachePath = sys_get_temp_dir() . '/json-rpc-php-blade-cache';
        $filesystem->ensureDirectoryExists($cachePath);

        $resolver->register('blade', static function () use ($filesystem, $cachePath) {
            return new CompilerEngine(new BladeCompiler($filesystem, $cachePath));
        });
        $resolver->register('php', static function () use ($filesystem) {
            return new \Illuminate\View\Engines\PhpEngine($filesystem);
        });

        return new Factory(
            $resolver,
            new FileViewFinder($filesystem, $locations),
            new Dispatcher(new Container())
        );
    }
}
