<?php

namespace Monomelodies\DNode;

use BadMethodCallException;
use React\Socket\ConnectionInterface;
use Evenement\EventEmitter;

class Remote extends EventEmitter
{
    /** @var object */
    private $wrapper;
    /** @var React\Socket\ConnectionInterface */
    private $connection;
    /** @var array */
    private $methods = [];
    /** @var int */
    private $callbackId = 0;

    public function __construct(Protocol $protocol, ConnectionInterface $connection)
    {
        $this->protocol = $protocol;
        $this->connection = $connection;
        echo 'requesting methods';
        $this->request('methods', [$this->protocol]);
    }

    public function request($method, $args)
    {
        // Wrap callbacks in arguments
        $scrub = $this->scrub($args);
        $request = [
            'method' => $method,
            'arguments' => $scrub['arguments'],
            'callbacks' => $scrub['callbacks'],
            'links' => $scrub['links']
        ];
        $this->connection->write(json_encode($request)."\n");
//        $this->emit('request', [$request]);
    }

    public function respond(string $event, object $response) : void
    {
        $this->connection->write(json_encode(compact('event', 'response'))."\n");
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

    private function scrub($obj) : array
    {
        $paths = [];
        $links = [];
        // TODO: Deep traversal
        foreach ($obj as $id => $node) {
            if (is_object($node)) {
                if ($node instanceof \Closure) {
                    $this->callbacks[$this->callbackId] = $node;
                    $paths[$this->callbackId] = [$id];
                    $this->callbackId++;
                    $obj[$id] = '[Function]';
                    continue;
                }
                $reflector = new \ReflectionClass($node);
                $methods = $reflector->getMethods();
                foreach ($methods as $method) {
                    if (!$method->isPublic() || $method->isConstructor() || $method->isDestructor()) {
                        continue;
                    }
                    $methodName = $method->getName();
                    $this->callbacks[$this->callbackId] = function() use ($methodName, $node) {
                        call_user_func_array([$node, $methodName], func_get_args());
                    };
                    $paths[$this->callbackId] = [$id, $methodName];
                    $this->callbackId++;
                    $node->$methodName = '[Function]';
                }
            }
        }
        return [
            'arguments' => $obj,
            'callbacks' => $paths,
            'links' => $links,
        ];
    }
}

