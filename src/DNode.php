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
        /*
        $client = @stream_socket_client($address);
        if (!$client) {
            return;
        }
        */
    }

    public function listen(string $address, callable $callback = null) : Server
    {
        $this->server = new Server($address, $this->loop);
        $this->server->on('connection', function (ConnectionInterface $connection) : void {
            $connection->on('request', function ($req) {
                var_dump($req);
            });
            $connection->on('data', function ($data) use ($connection) {
                var_dump($data);
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
}

