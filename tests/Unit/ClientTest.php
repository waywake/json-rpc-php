<?php

namespace Tests\Unit;

use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Psr7\Utils;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Foundation\Application;
use JsonRpc\Client;
use JsonRpc\Exception\RpcServerException;
use JsonRpc\JsonRpc;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\ThrowingStream;

class ClientTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_SERVER['HTTP_X_REQUEST_ID']);
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testItPostsJsonRpcPayloadAndReturnsResult(): void
    {
        $_SERVER['HTTP_X_REQUEST_ID'] = 'req-123';

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => ['ok' => true],
                'id' => 1,
            ])),
        ]);

        $client = $this->clientWithMock($mock);

        $this->assertSame(['ok' => true], $client->call('user.info', [123]));

        $request = $mock->getLastRequest();
        $this->assertSame('POST', $request->getMethod());
        $this->assertSame('/rpc/json-rpc-v2.json', $request->getUri()->getPath());
        $this->assertSame('app=demo-app', $request->getUri()->getQuery());
        $this->assertSame('demo-app', $request->getHeaderLine('X-Client-App'));
        $this->assertSame('req-123', $request->getHeaderLine('X-Request-Id'));

        $this->assertSame([
            'jsonrpc' => '2.0',
            'method' => 'user.info',
            'params' => [123],
            'id' => 1,
        ], json_decode((string) $request->getBody(), true));
    }

    public function testItThrowsRpcServerExceptionForJsonRpcError(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpc::Rpc_Error_Invalid_Params,
                    'message' => 'Invalid params',
                ],
                'id' => 1,
            ])),
        ]);

        $client = $this->clientWithMock($mock);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionCode(JsonRpc::Rpc_Error_Invalid_Params);
        $this->expectExceptionMessage('Invalid params');

        $client->call('user.info', []);
    }

    public function testItUsesFallbackRequestIdWhenHeaderIsMissing(): void
    {
        unset($_SERVER['HTTP_X_REQUEST_ID']);

        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => 'ok',
                'id' => 1,
            ])),
        ]);

        $client = $this->clientWithMock($mock);
        $this->assertSame('ok', $client->call('ping', []));

        $this->assertSame('nginx-config-err', $mock->getLastRequest()->getHeaderLine('X-Request-Id'));
    }

    public function testItSupportsMagicMethodCalls(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'result' => 'pong',
                'id' => 1,
            ])),
        ]);

        $client = $this->clientWithMock($mock);

        $this->assertSame('pong', $client->ping());
        $this->assertSame('ping', json_decode((string) $mock->getLastRequest()->getBody(), true)['method']);
    }

    public function testItThrowsSystemErrorForEmptyHttpResponse(): void
    {
        $mock = new MockHandler([
            new Response(200, [], ''),
        ]);

        $client = $this->clientWithMock($mock);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionCode(JsonRpc::Rpc_Error_System_Error);
        $this->expectExceptionMessage('http response empty');

        $client->call('empty.response', []);
    }

    public function testItJsonEncodesArrayErrorMessages(): void
    {
        $mock = new MockHandler([
            new Response(200, [], json_encode([
                'jsonrpc' => '2.0',
                'error' => [
                    'code' => JsonRpc::Rpc_Error_System_Error,
                    'message' => ['reason' => 'boom'],
                ],
                'id' => 1,
            ])),
        ]);

        $client = $this->clientWithMock($mock);

        $this->expectException(RpcServerException::class);
        $this->expectExceptionCode(JsonRpc::Rpc_Error_System_Error);
        $this->expectExceptionMessage('{"reason":"boom"}');

        $client->call('array.error', []);
    }

    public function testItWrapsHttpServerExceptionsAndKeepsResponseInDebugMode(): void
    {
        $this->bindDebugConfig(true);

        $mock = new MockHandler([
            new Response(500, [], 'server exploded'),
        ]);

        $client = $this->clientWithMock($mock);

        try {
            $client->call('server.error', []);
            $this->fail('Expected RpcServerException was not thrown.');
        } catch (RpcServerException $exception) {
            $this->assertSame(JsonRpc::Rpc_Error_Internal_Error, $exception->getCode());
            $this->assertSame('Internal error', $exception->getMessage());
            $this->assertSame(500, $exception->getResponse()->getStatusCode());
        }
    }

    public function testItWrapsHttpServerExceptionsWithoutResponseOutsideDebugMode(): void
    {
        $this->bindDebugConfig(false);

        $mock = new MockHandler([
            new Response(500, [], 'server exploded'),
        ]);

        $client = $this->clientWithMock($mock);

        try {
            $client->call('server.error', []);
            $this->fail('Expected RpcServerException was not thrown.');
        } catch (RpcServerException $exception) {
            $this->assertSame(JsonRpc::Rpc_Error_Internal_Error, $exception->getCode());
            $this->assertNull($exception->getResponse());
        }
    }

    public function testItWrapsDecodeExceptionsAndKeepsResponseInDebugMode(): void
    {
        $this->bindDebugConfig(true);

        $mock = new MockHandler([
            new Response(200, [], new ThrowingStream(Utils::streamFor('bad stream'))),
        ]);

        $client = $this->clientWithMock($mock);

        try {
            $client->call('decode.error', []);
            $this->fail('Expected RpcServerException was not thrown.');
        } catch (RpcServerException $exception) {
            $this->assertSame(JsonRpc::Rpc_Error_Parse_Error, $exception->getCode());
            $this->assertSame('stream decode failed', $exception->getMessage());
            $this->assertSame(200, $exception->getResponse()->getStatusCode());
        }
    }

    private function clientWithMock(MockHandler $mock): Client
    {
        return (new Client([
            'app' => 'demo-app',
            'client' => [
                'default' => [
                    'base_uri' => 'http://rpc.test',
                    'handler' => HandlerStack::create($mock),
                ],
            ],
        ]))->endpoint('default');
    }

    private function bindDebugConfig(bool $debug): void
    {
        $app = new Application(dirname(__DIR__, 2));
        $app->instance('config', new Repository([
            'app' => [
                'debug' => $debug,
            ],
        ]));
        Container::setInstance($app);
    }
}
