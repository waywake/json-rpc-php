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
####RPC目录层级 
```

./app/
├── Console
├── Events
├── Exceptions
├── Http
│   ├── Controllers
│   │   ├── DFAPI
│   │   ├── DSPAPI
│   │   ├── ErpAPI
│   ├── Middleware
│   └── Resources
├── Jobs
├── Listeners
├── Logging
├── Logic
├── Models
├── Observers
├── Providers
│   ├── AliyunServiceProvider.php
│   ├── AppServiceProvider.php
│   ├── AuthServiceProvider.php
│   ├── EventServiceProvider.php
│   ├── ObserverServiceProvider.php
│   ├── OssServiceProvider.php
│   └── WechatOauthServiceProvider.php
├── Rpc
│   ├── Order
│   │   └── RpcOrder.php
│   ├── User
│   │   └── RpcUser.php
│   └── method.php //配置文件

```
####method.php
```php
return [
    //方法名 => [ 类名, 函数名 ]
    'user.info' => [\App\Rpc\User\RpcUser::class, 'getUserInfo'],
    'user.id' => [\App\Rpc\User\RpcUser::class, 'getUserId'],
    'user.relatison' => [\App\Rpc\User\RpcUser::class, 'getUserId'],
    'order.info' => [\App\Rpc\Order\RpcOrder::class, 'getOrderInfo'],
];
```

####rpc server文件
```php
<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/8
 * Time: 11:49 AM
 */
namespace App\Rpc\User;

use JsonRpc\Server\JsonRpcMethod;

/**
 * Class RpcUser
 * @title 用户rpc
 * @package App\Rpc\User
 */
class RpcUser extends JsonRpcMethod
{
    /**
     * @title 获取用户信息
     * @url user.info
     * @method POST
     * @param int uid 用户id 空 必须
     * @param string password 密码
     * @return int code 状态码（具体参见状态码说明）
     */
    public function getUserInfo($uid)
    {
        return $this->response([$uid."abcdefg",123,321,321,3123,1]);
    }

    /**
     * @title 用户登录API
     * @url https://wwww.baidu.com/login
     * @method POST
     * @param string username 账号 空 必须
     * @param string password 密码 空 必须
     * @code 1 成功
     * @code 2 失败
     * @return int code 状态码（具体参见状态码说明）
     * @return string msg 提示信息
     */
    public function getUserId($uid)
    {
        return $this->response([$uid."11ss",1]);
    }
}

```

####工具
```
http://host/rpc/tool.html 调用工具
http://host/rpc/doc.html 文档地址
```