<?php
return [
    /**
     * app name
     */
    'app' => env('APP_NAME'),

    /**
     * log 存储路径
     */
    'log_path' => storage_path('logs/rpc.log'),//rpc日志路径

    /**
     * json rpc server 配置
     */
    'server' => [
        'name' => env('APP_NAME'),
        'map' => base_path('app/Rpc/method.php'), //rpc注册文件
    ],
    /**
     * json rpc client 配置
     */
    'client' => [
        'auth' => [
            'local' => true,
            'base_uri' => env('RPC_AUTH_URI','http://auth.dev.haowumc.com'),
        ],
        'erp' => [
            'local' => true,
            'base_uri' => env('RPC_ERP_URI','http://erp.dev.haowumc.com'),
        ],
        'crm' => [
            'local' => true,
            'base_uri' => env('RPC_CRM_URI','http://crm.dev.haowumc.com'),
        ],
        'api' => [
            'local' => true,
            'base_uri' => env('RPC_API_URI','http://api.dev.haowumc.com'),
        ],
        'op' => [
            'local' => true,
            'base_uri' => env('RPC_OP_URI','http://op.dev.haowumc.com'),
        ],
    ],
];
