<?php

namespace Tests\Unit;

use Illuminate\Container\Container;
use Illuminate\Http\Request;
use JsonRpc\JsonRpc;
use JsonRpc\Server\JsonRpcServer;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Tests\Fixtures\EchoRpc;

class JsonRpcServerTest extends TestCase
{
    protected function tearDown(): void
    {
        Container::setInstance(null);
        parent::tearDown();
    }

    public function testItHandlesPostRequestsWithPositionalParams(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => [2, 5],
            'id' => 7,
        ])));

        $response = $this->server()->handler();

        $this->assertSame([
            'jsonrpc' => '2.0',
            'result' => 7,
            'id' => 7,
        ], $response->getData(true));
    }

    public function testItNormalizesNamedParamsInMethodSignatureOrder(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'text.join',
            'params' => [
                'second' => 'world',
                'first' => 'hello',
            ],
            'id' => 'named',
        ])));

        $response = $this->server()->handler();

        $this->assertSame([
            'jsonrpc' => '2.0',
            'result' => 'hello:world',
            'id' => 'named',
        ], $response->getData(true));
    }

    public function testItUsesDefaultParamsWhenOptionalArgumentIsMissing(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'text.join',
            'params' => ['hello'],
            'id' => 3,
        ])));

        $response = $this->server()->handler();

        $this->assertSame('hello:fallback', $response->getData(true)['result']);
    }

    public function testItHandlesGetRequestsWithJsonEncodedParams(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'GET', [
            'method' => 'math.add',
            'params' => '[4,6]',
            'id' => 'get-1',
        ]));

        $response = $this->server()->handler();

        $this->assertSame([
            'jsonrpc' => '2.0',
            'result' => 10,
            'id' => 'get-1',
        ], $response->getData(true));
    }

    public function testItRejectsNonJsonPostRequests(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST'));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_Invalid_Request, $response->getData(true)['error']['code']);
    }

    public function testItRejectsInvalidJsonPayload(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], '{bad json'));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_Parse_Error, $response->getData(true)['error']['code']);
    }

    public function testItRejectsUnknownMethods(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'missing.method',
            'params' => [],
            'id' => 5,
        ])));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_NOT_FOUND, $response->getData(true)['error']['code']);
    }

    public function testItRejectsMissingRequiredParams(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => [1],
            'id' => 9,
        ])));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_Invalid_Params, $response->getData(true)['error']['code']);
    }

    public function testItRejectsInvalidGetParamsJson(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'GET', [
            'method' => 'math.add',
            'params' => '{bad json',
            'id' => 'bad-get',
        ]));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_Parse_Error, $response->getData(true)['error']['code']);
    }

    public function testItRejectsNonArrayParams(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => 'not-array',
            'id' => 10,
        ])));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_Invalid_Params, $response->getData(true)['error']['code']);
        $this->assertSame(10, $response->getData(true)['id']);
    }

    public function testItRejectsJsonRpcBatchPayloads(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            [
                'jsonrpc' => '2.0',
                'method' => 'math.add',
                'params' => [1, 2],
                'id' => 1,
            ],
        ])));

        $response = $this->server()->handler();

        $this->assertSame(JsonRpc::Rpc_Error_Parse_Error, $response->getData(true)['error']['code']);
    }

    public function testItLeavesEmptyParamsUntouchedForNoArgMethods(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'noop',
            'params' => [],
            'id' => 11,
        ])));

        $response = $this->server()->handler();

        $this->assertSame('no-args', $response->getData(true)['result']);
    }

    public function testItFallsBackToPositionalParamsForUnknownNamedParamKeys(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'text.join',
            'params' => [
                'first' => 'left',
                'unknown' => 'right',
            ],
            'id' => 12,
        ])));

        $response = $this->server()->handler();

        $this->assertSame('left:right', $response->getData(true)['result']);
    }

    public function testItLogsServerRequestsAndResponsesWhenLoggerIsSet(): void
    {
        $this->bindRequest(Request::create('/rpc/json-rpc-v2.json', 'POST', [], [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'jsonrpc' => '2.0',
            'method' => 'math.add',
            'params' => [1, 2],
            'id' => 13,
        ])));

        $server = $this->server();
        $server->setLogger(new NullLogger());

        $this->assertSame(3, $server->handler()->getData(true)['result']);
    }

    private function server(): JsonRpcServer
    {
        return new JsonRpcServer([
            'name' => 'demo',
            'map' => [
                'math.add' => [EchoRpc::class, 'add'],
                'text.join' => [EchoRpc::class, 'join'],
                'noop' => [EchoRpc::class, 'noArgs'],
            ],
        ]);
    }

    private function bindRequest(Request $request): void
    {
        $container = new Container();
        $container->instance('request', $request);
        Container::setInstance($container);
    }
}
