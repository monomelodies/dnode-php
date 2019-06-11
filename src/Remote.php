<?php

namespace Monomelodies\DNode;

use BadMethodCallException;
use React\Socket\ConnectionInterface;

class Remote
{
    /** @var object */
    private $wrapper;
    /** @var React\Socket\ConnectionInterface */
    private $connection;
    /** @var array */
    private $methods = [];

    public function __construct(object $wrapper, ConnectionInterface $connection)
    {
        $this->wrapper = $wrapper;
        $this->connection = $connection;
    }

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

