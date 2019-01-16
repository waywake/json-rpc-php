# Paidian PHP RPC


> 该项目使用 composer 来完成加载



执行 
```bash
composer config repositories.php-json-rpc vcs git@git.int.haowumc.com:composer/php-json-rpc.git
composer require paidian/json-rpc
```


### 代码中启用

* 注册服务

```php
$app->register(\JsonRpc\Providers\LumenServerServiceProvider::class); //rpc server
$app->register(\JsonRpc\Providers\ClientServiceProvider::class); // rpc client
```

### 配置

```
Rpc目录层级 
app
└───Console/
└───Http/
└───Jobs/
└───.....
└───Rpc/
└───────User/
|     |    |  RpcUser.php
└───────Order/
|     |   method.php
```
