<?php

namespace Monomelodies\DNode;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use React\Socket\{ Connection, ConnectionInterface, Connector };
use React\Promise\Promise;
use stdClass;
use DomainException;
use RuntimeException;

class DNode extends EventEmitter
{
    /** @var array */
    public $stack = [];
    /** @var React\EventLoop\LoopInterface */
    private $loop;
    /** @var Monomelodies\DNode\Protocol */
    private $protocol;

    public function __construct(LoopInterface $loop, object $wrapper = null)
    {
        $this->loop = $loop;
        $this->protocol = new Protocol($wrapper ?? new stdClass);
    }

    public function using(callable $middleware) : DNode
    {
        $this->stack[] = $middleware;
        return $this;
    }

    public function connect(string $address, callable $callback = null) : Promise
    {
        $connector = new Connector($this->loop);
        return $connector
            ->connect($address)
            ->then(function (ConnectionInterface $connection) : Remote {
                $connection->on('data', function ($data) : void {
                    var_dump($data);
                });
                return new Remote($this->protocol, $connection);
            })
            ->otherwise(function ($reason) use ($address) : void {
                var_dump($reason->getMessage());
                var_dump(get_class($reason));
                $e = new RuntimeException("No connection to DNode server in $address");
                $this->emit('error', [$e]);
                if (!count($this->listeners('error'))) {
                    trigger_error((string) $e, E_USER_ERROR);
                }
            });
    }

    public function listen(string $address, callable $callback = null) : Server
    {
        $this->server = new Server($address, $this->loop);
        $this->server->on('connection', function (ConnectionInterface $connection) : void {
            $this->remote = new Remote($this->protocol, $connection);
            $connection->on('request', function ($req) {
                var_dump($req);
            });
            $connection->on('data', function ($data) use ($connection) {
                $request = json_decode($data);
                $this->handle($request);
//                $this->emit($data->event, $data->data);
            });
        });
        return $this->server;
    }

    public function handleConnection(ConnectionInterface $conn, array $params) : void
    {
        $client = $this->protocol->create();

        $onReady = $params['block'] ?? null;
        $stream = new Stream($this, $conn, $client, $onReady);

        $conn->pipe($stream->stream)->pipe($conn);
        $conn->resume();

        $client->start();
    }

    public function end() : void
    {
        $this->protocol->end();
        $this->emit('end');
    }

    public function close() : void
    {
        $this->server->close();
    }

    private function handle($req) : void
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

    /**
     * Replace callbacks. The supplied function should take a callback
     * id and return a callback of its own.
     */
    private function unscrub(object $msg) : array
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

    private function handleMethods($methods) : void
    {
        if (!is_object($methods)) {
            $methods = new stdClass;
        }
        foreach ($methods as $key => $value) {
            $this->remote->setMethod($key, $value);
        }
        $this->remote->respond('remote', $this->remote);
        $this->ready = true;
    }
}

