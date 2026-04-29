<?php

namespace Tests\Fixtures;

use JsonRpc\JsonRpc;
use JsonRpc\Server\JsonRpcMethod;

class EchoRpc extends JsonRpcMethod
{
    public function noArgs(): array
    {
        return $this->response('no-args');
    }

    public function add(int $left, int $right): array
    {
        return $this->response($left + $right);
    }

    public function join(string $first, string $second = 'fallback'): array
    {
        return $this->response($first . ':' . $second);
    }

    public function failWithData(): array
    {
        return $this->error(JsonRpc::Rpc_Error_System_Error, ['reason' => 'boom']);
    }

    /**
     * @title Documented method
     * @param string name 用户名 空 是
     * @return string message 提示信息
     * @code 0 成功
     */
    public function documented(string $name): array
    {
        return $this->response('hello ' . $name);
    }
}
