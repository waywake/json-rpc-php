<?php

namespace Tests\Unit;

use Illuminate\Http\Request;
use JsonRpc\JsonRpc;
use PHPUnit\Framework\TestCase;
use Tests\Fixtures\EchoRpc;

class JsonRpcMethodTest extends TestCase
{
    public function testItBuildsSuccessResponse(): void
    {
        $method = new EchoRpc(99, Request::create('/'));

        $this->assertSame([
            'jsonrpc' => '2.0',
            'result' => ['value' => 1],
            'id' => 99,
        ], $method->response(['value' => 1]));
    }

    public function testItBuildsStringErrorResponse(): void
    {
        $method = new EchoRpc('abc', Request::create('/'));

        $this->assertSame([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => JsonRpc::Rpc_Error_Invalid_Params,
                'message' => 'bad params',
            ],
            'id' => 'abc',
        ], $method->error(JsonRpc::Rpc_Error_Invalid_Params, 'bad params'));
    }

    public function testItBuildsDataErrorResponse(): void
    {
        $method = new EchoRpc(1, Request::create('/'));

        $this->assertSame([
            'jsonrpc' => '2.0',
            'error' => [
                'code' => JsonRpc::Rpc_Error_System_Error,
                'message' => 'System error',
                'data' => ['reason' => 'boom'],
            ],
            'id' => 1,
        ], $method->error(JsonRpc::Rpc_Error_System_Error, ['reason' => 'boom']));
    }
}
