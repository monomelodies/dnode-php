<?php

namespace Monomelodies\DNode;

use Evenement\EventEmitter;
use React\Socket\ServerInterface;

class ServerStub extends EventEmitter implements ServerInterface
{
    public function listen($port, $host = '127.0.0.1')
    {
    }

    public function getPort()
    {
    }

    public function shutdown()
    {
    }

    public function getAddress()
    {
    }

    public function pause()
    {
    }

    public function resume()
    {
    }

    public function close()
    {
    }
}
