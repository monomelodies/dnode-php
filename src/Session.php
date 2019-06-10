<?php

namespace Monomelodies\DNode;

use Evenement\EventEmitter;

class Session extends EventEmitter
{
    // Session ID
    public $id = '';

    // Wrapped local callbacks, by callback ID
    private $callbacks = [];

    // Latest callback ID used
    private $cbId = 0;

    // Remote methods that were wrapped, by callback ID
    private $wrapped = [];

    // Wrapped object
    private $wrapper;

    // Remote methods
    public $remote;

    // Whether the session is ready for operation
    public $ready = false;

    public function __construct(string $id, object $wrapper)
    {
        $this->id = $id;
        $this->wrapper = $wrapper;
        $this->remote = new RemoteProxy;
        $this->wrapper->remote =& $this->remote;
    }

    public function start() : void
    {
        // Send our methods to the other party
        $this->request('methods', [$this->wrapper]);
    }

    public function end() : void
    {
        $this->emit('end');
        $this->removeAllListeners();

        $this->callbacks = [];
        $this->wrapped = [];
        $this->remote = null;
        $this->wrapper = null;
    }

    public function request(string $method, array $args) : void
    {
        // Wrap callbacks in arguments
        $scrub = $this->scrub($args);

        $request = array(
            'method' => $method,
            'arguments' => $scrub['arguments'],
            'callbacks' => $scrub['callbacks'],
            'links' => $scrub['links']
        );

        $this->emit('request', [$request]);
    }

    public function parse($line) : void
    {
        // TODO: Error handling for JSON parsing
        $msg = json_decode($line);
        // TODO: Try/catch handle
        $this->handle($msg);
    }

    public function handle($req) : void
    {
        $session = $this;

        // Register callbacks from request
        $args = $this->unscrub($req);

        if ($req->method === 'methods') {
            // Got a methods list from the remote
            $this->handleMethods($args[0]);
            return;
        }
        if ($req->method === 'error') {
            // Got an error from the remote
            $this->emit('remoteError', [$args[0]]);
            return;
        }
        if (is_string($req->method)) {
            if (is_callable([$this, $req->method])) {
                call_user_func_array([$this, $req->method], $args);
                return;
            }
            $this->emit('error', ["Request for non-enumerable method: {$req->method}"]);
            return;
        }
        if (is_numeric($req->method)) {
            call_user_func_array($this->callbacks[$req->method], $args);
        }
    }

    private function handleMethods($methods) : void
    {
        if (!is_object($methods)) {
            $methods = new \StdClass();
        }

        foreach ($methods as $key => $value) {
            $this->remote->setMethod($key, $value);
        }

        $this->emit('remote', [$this->remote]);
        $this->ready = true;
        $this->emit('ready');
    }

    private function scrub(array $obj) : array
    {
        $paths = [];
        $links = [];

        // TODO: Deep traversal
        foreach ($obj as $id => $node) {
            if (is_object($node)) {
                if ($node instanceof \Closure) {
                    $this->callbacks[$this->cbId] = $node;
                    $paths[$this->cbId] = [$id];
                    $this->cbId++;
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

                    $this->callbacks[$this->cbId] = function() use ($methodName, $node) {
                        call_user_func_array([$node, $methodName], func_get_args());
                    };
                    $paths[$this->cbId] = [$id, $methodName];
                    $this->cbId++;
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

    /**
     * Replace callbacks. The supplied function should take a callback
     * id and return a callback of its own.
     */
    private function unscrub($msg) : array
    {
        $args = $msg->arguments;
        $session = $this;
        foreach ($msg->callbacks as $id => $path) {
            if (!isset($this->wrapped[$id])) {
                $this->wrapped[$id] = function() use ($session, $id) {
                    $session->request((int) $id, func_get_args());
                };
            }
            $location =& $args;
            foreach ($path as $part) {
                if (is_array($location)) {
                    $location =& $location[$part];
                    continue;
                }
                $location =& $location->$part;
            }
            $location = $this->wrapped[$id];
        }
        return $args;
    }
}

