<?php

namespace DNode;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use React\Socket\Server;
use React\Socket\Connection;
use React\Socket\ConnectionInterface;

class DNode extends EventEmitter
{
    public $stack = [];

    private $loop;
    private $protocol;

    public function __construct(LoopInterface $loop, object $wrapper = null)
    {
        $this->loop = $loop;

        $wrapper = $wrapper ?: new \StdClass();
        $this->protocol = new Protocol($wrapper);
    }

    public function using($middleware) : DNode
    {
        $this->stack[] = $middleware;
        return $this;
    }

    public function connect(...$args) : void
    {
        $params = $this->protocol->parseArgs($args);
        if (!isset($params['host'])) {
            $params['host'] = '127.0.0.1';
        }

        if (!isset($params['port'])) {
            throw new \Exception("For now we only support TCP connections to a defined port");
        }

        $client = @stream_socket_client("tcp://{$params['host']}:{$params['port']}");
        if (!$client) {
            $e = new \RuntimeException("No connection to DNode server in tcp://{$params['host']}:{$params['port']}");
            $this->emit('error', [$e]);

            if (!count($this->listeners('error'))) {
                trigger_error((string) $e, E_USER_ERROR);
            }

            return;
        }

        $conn = new Connection($client, $this->loop);
        $this->handleConnection($conn, $params);
    }

    public function listen(...$args) : Server
    {
        $params = $this->protocol->parseArgs($args);
        if (!isset($params['host'])) {
            $params['host'] = '127.0.0.1';
        }

        if (!isset($params['port'])) {
            throw new \Exception("For now we only support TCP connections to a defined port");
        }

        $this->server = new Server("{$params['host']}:{$params['port']}", $this->loop);
        $this->server->on('connection', function ($conn) use ($params) {
            $this->handleConnection($conn, $params);
        });

        return $this->server;
    }

    public function handleConnection(ConnectionInterface $conn, array $params) : void
    {
        $client = $this->protocol->create();

        $onReady = isset($params['block']) ? $params['block'] : null;
        $stream = new Stream($this, $conn, $client, $onReady);

        $conn->pipe($stream->stream)->pipe($conn);

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

