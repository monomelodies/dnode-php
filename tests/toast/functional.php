<?php

use React\EventLoop\StreamSelectLoop;
use Gentry\Gentry\Wrapper;

/** Functionally test complete server/client */
return function () : Generator {
    /**
     * @covers DNode\DNode::__construct
     * @covers DNode\DNode::connect
     * @covers DNode\DNode::listen
     * @test
     */
    /** Using a server and a client, we can respond to a request */
    yield function () {
        $captured = null;

        $loop = new StreamSelectLoop;
        $server = Wrapper::createObject(Monomelodies\DNode\DNode::class, $loop, new Monomelodies\DNode\Transformer);
        $socket = $server->listen('tcp://127.0.0.1:5004');

        $client = Wrapper::createObject(Monomelodies\DNode\DNode::class, $loop);
        $client->connect('tcp://127.0.0.1:5004', function ($remote, $conn) use (&$captured, $socket) {
            $remote->transform('fou', function ($transformed) use ($conn, &$captured, $socket) {
                $captured = $transformed;
                $conn->end();
                $socket->shutdown();
            });
        });
        $loop->run();

        assert('FOO' === $captured);
    };
};

