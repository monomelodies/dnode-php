<?php

namespace Monomelodies\DNode;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use React\Socket\Connection;
use React\Socket\ConnectionInterface;
use stdClass;
use DomainException;
use RuntimeException;

class DNode extends EventEmitter
{
    public $stack = [];

    private $loop;
    private $protocol;

    public function __construct(LoopInterface $loop, object $wrapper = null)
    {
        $this->loop = $loop;
        $wrapper = $wrapper ?: new stdClass;
        $this->protocol = new Protocol($wrapper);
    }

    public function using($middleware) : DNode
    {
        $this->stack[] = $middleware;
        return $this;
    }

    public function connect(string $address, callable $callback = null) : void
    {
        $client = @stream_socket_client($address);
        if (!$client) {
            $e = new RuntimeException("No connection to DNode server in $address");
            $this->emit('error', [$e]);
            if (!count($this->listeners('error'))) {
                trigger_error((string) $e, E_USER_ERROR);
            }
            return;
        }
    }

    public function listen(string $address, callable $callback = null) : Server
    {
        $this->server = new Server($address, $this->loop);
        $this->server->on('connection', function ($connection) {
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

    public function getLoop() : LoopInterface
    {
        return $this->loop;
    }
}

