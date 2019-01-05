<?php

namespace JsonRpc\Server;

class JsonRpcTool
{
    public function __construct()
    {
    }

    public function render()
    {
        return file_get_contents('abc.html');
    }

}