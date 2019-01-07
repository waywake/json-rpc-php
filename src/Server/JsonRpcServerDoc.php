<?php

namespace JsonRpc\Server;

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
            $methods[] = [
                'method' => $key,
                'desc' => $this->desc($item[0], $item[1]),
            ];
        }
        return $methods;
    }

    public function render()
    {

        /**
         * @var $view Factory
         */
        $view = view();

        dump($this->methods());
        exit;

        $view->share('methods', $this->methods());

        return $view->exists('doc') ?
            $view->make('doc') :
            $view->file(__DIR__ . '/../views/doc.blade.php');
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