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

    public function __construct(Protocol $protocol, ConnectionInterface $connection)
    {
        $this->protocol = $protocol;
        $this->connection = $connection;
    }

    public function getMethods() : array
    {
        return $this->methods;
    }

    public function setMethod(string $method, callable $closure) : void
    {
        $this->methods[$method] = $closure;
    }

    public function close() : void
    {
        $this->connection->close();
    }

    public function __call($method, $args)
    {
        if (!isset($this->methods[$method])) {
            throw new BadMethodCallException("Method {$method} not available");
        }
        call_user_func($this->methods[$method], ...$args);
    }
}

