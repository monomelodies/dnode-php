<?php

namespace Monomelodies\DNode;

use BadMethodCallException;

class RemoteProxy
{
    private $methods = [];

    public function getMethods()
    {
      return $this->methods;
    }

    public function setMethod($method, $closure)
    {
        $this->methods[$method] = $closure;
    }

    public function __call($method, $args)
    {
        if (!isset($this->methods[$method])) {
            throw new BadMethodCallException("Method {$method} not available");
        }
        call_user_func($this->methods[$method], ...$args);
    }
}

