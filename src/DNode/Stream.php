<?php
namespace DNode;

use React\Stream\{ WritableResourceStream, CompositeStream };
use React\Socket\ConnectionInterface;

class Stream
{
    private $dnode;

    public function __construct(DNode $dnode, ConnectionInterface $conn, Session $client, callable $onReady = null)
    {
        $this->dnode = $dnode;

        foreach ($this->dnode->stack as $middleware) {
            call_user_func($middleware, [$client->instance, $client->remote, $client]);
        }

        $input = new InputStream($conn->stream, $this->dnode->getLoop());
        $output = new WritableResourceStream($conn->stream, $this->dnode->getLoop());
        $client->on('request', function (array $request) use ($output) : void {
            $output->emit('data', [json_encode($request)."\n"]);
        });

        $this->stream = new CompositeStream($input, $output);

        if ($onReady) {
            $client->on('connection', function () use ($client, $onReady) {
                call_user_func($onReady, $client->remote, $client);
            });
        }
    }

    public function __call($method, array $args)
    {
        return call_user_func_array([$this->stream, $method], $args);
    }
}
