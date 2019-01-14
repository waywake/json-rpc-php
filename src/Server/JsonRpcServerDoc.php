<?php

namespace JsonRpc\Server;

use itxq\apidoc\BootstrapApiDoc;

class JsonRpcDoc
{

    protected $config;

    protected $classes;

    public function __construct($config)
    {
        $this->map = include $config['map'];
    }

    public function methods()
    {
        $methods = [];
        foreach ($this->map as $key => $item) {
            if (!in_array($item[0], $methods)) {
                $methods[] = $item[0];
            }
        }
        return $methods;
    }

    public function render()
    {

        /**
         * @var $view Factory
         */
       $config = [
           'class' => $this->methods(),
           'filter_method' => [],
       ];
       $api = new BootstrapApiDoc($config);
       $doc = $api->getHtml();
       exit($doc);
    }

    protected function desc($class, $method)
    {
        if (!isset($this->classes[$class])) {
            $reflector = new \ReflectionClass($class);
            $this->classes[$class] = $reflector;
        } else {
            $reflector = $this->classes[$class];
        }

        return str_replace("/**\n",'',$reflector->getMethod($method)->getDocComment());
    }

}