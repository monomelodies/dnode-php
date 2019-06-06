<?php
namespace DNode;

use React\Stream\{ ReadableResourceStream, WriteableResourceStream, CompositeStream };

class Stream
{
    private $dnode;

    public function __construct(DNode $dnode, Session $client, $onReady)
    {
        $this->dnode = $dnode;

        foreach ($this->dnode->stack as $middleware) {
            call_user_func($middleware, array($client->instance, $client->remote, $client));
        }

        if ($onReady) {
            $client->on('ready', function () use ($client, $onReady) {
                call_user_func($onReady, $client->remote, $client);
            });
        }

        $input = new ReadableResourceStream($client, $this->dnode->getLoop());
        $output = new WritableResourceStream($client, $this->dnode->getLoop());
        $client->on('request', function (array $request) use ($output) {
            $output->emit('data', array(json_encode($request)."\n"));
        });

        $this->stream = new CompositeStream($output, $input);
    }

    public function __call($method, array $args)
    {
        return call_user_func_array([$this->stream, $method], $args);
    }
}
