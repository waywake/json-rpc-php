<?php

namespace JsonRpc\Server;

class JsonRpcDoc
{

    protected $methods = [];

    public function __construct($dir)
    {
        $this->methods = include $dir . '/methods.php';
    }

    public function methods()
    {
        return $this->methods;
    }

    public function render()
    {
        return view('doc', ['doc' => $this]);
    }

}