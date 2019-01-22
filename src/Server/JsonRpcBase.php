<?php

namespace JsonRpc\Server;

class JsonRpcBase
{
    const Rpc_Error_Parse_Error = -32700; //Parse error语法解析错误 服务端接收到无效的json。该错误发送于服务器尝试解析json文本
    const Rpc_Error_Invalid_Request = -32600; //Invalid Request无效请求	发送的json不是一个有效的请求对象。
    const Rpc_Error_NOT_FOUND = -32601;//Method not found找不到方法
    const Rpc_Error_Invalid_Params = -32602; //Invalid params无效的参数
    const Rpc_Error_Internal_Error = -32603;//Internal error内部错误
    const Rpc_Error_System_Error = -32400; // system error 业务产生错误

    /**
     * -32000 to 32099 自定义错误
     */
    const Rpc_Success = 0;


    const ErrorMsg = [
        self::Rpc_Error_NOT_FOUND => 'Method not found',
        self::Rpc_Error_Parse_Error => 'Json parse error',
        self::Rpc_Error_Invalid_Request => 'Invalid request',
        self::Rpc_Error_Invalid_Params => 'Invalid params',
        self::Rpc_Error_Internal_Error => 'Internal error',
        self::Rpc_Error_System_Error => 'System error',
        self::Rpc_Success => 'Success'
    ];
}