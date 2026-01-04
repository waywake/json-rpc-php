<?php
/**
 * Created by PhpStorm.
 * User: dongwei
 * Date: 2019/1/14
 * Time: 11:45 AM
 */

namespace JsonRpc\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class Security
{
    public function __construct()
    {
    }


    /**
     * @param Request $request
     * @param Closure $next
     * @return JsonResponse|mixed
     */
    public function handle(Request $request, Closure $next): mixed
    {
        $ip = $request->getClientIp();
        if ($this->isClientIPPermitted($ip) == false) {
            return new JsonResponse("$ip is forbidden", 403);
        }
        return $next($request);
    }

    /**
     * 内网ip判断
     * @param string $ip
     * @return bool
     */
    private function isClientIPPermitted(string $ip): bool
    {
        if (app()->environment('develop', 'local')) {
            return true;
        }

        if (Str::startsWith($ip, [
            '127.0.0.1',
            //局域网
            '192.168.',
            //vpc
            '10.0.',
            //pod network
            '172.20.',
            //北京办公区
            '172.16.'
        ])) {
            return true;
        }
        return false;
    }
}
