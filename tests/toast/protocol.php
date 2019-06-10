<?php

use Gentry\Gentry\Wrapper;

/** Tests for DNode protocol */
return function () : Generator {
    $protocol = Wrapper::createObject(Monomelodies\DNode\Protocol::class, new Monomelodies\DNode\Dog);

    /** When "creating" a protocol, we get a session */
    yield function () use ($protocol) {
        $session = $protocol->create();
        assert($session instanceof Monomelodies\DNode\Session);
    };

    /** We can destroy a session TODO: actually assert something... */
    yield function () use ($protocol) {
        $session = $protocol->create();
        $protocol->destroy($session->id);
    };

    /** When creating multiple sessions they can all be ended seperately */
    yield function () use ($protocol) {
        $sessions = [
            $protocol->create(),
            $protocol->create(),
        ];

        $called = 0;
        foreach ($sessions as $session) {
            $session->on('end', function () use (&$called) {
                $called++;
            });
        }

        $protocol->end();
        assert($called === 2);
    };

    /** Parse arguments... but this can prolly go */
    yield function () use ($protocol) {
        //assert($expected === $protocol->parseArgs($args));
    };

    yield function () use ($protocol) {
        $closure = function () {};
        $server = new Monomelodies\DNode\ServerStub;

        $obj = new \stdClass();
        $obj->foo = 'bar';
        $obj->baz = 'qux';

        return [
            'string number becomes port' => [
                ['port' => '8080'],
                ['8080'],
            ],
            'leading / becomes path' => [
                ['path' => '/foo'],
                ['/foo'],
            ],
            'string becomes host' => [
                ['host' => 'foo'],
                ['foo'],
            ],
            'integer becomes port' => [
                ['port' => 8080],
                [8080],
            ],
            'Closure becomes block' => [
                ['block' => $closure],
                [$closure],
            ],
            'ServerInterface becomes server' => [
                ['server' => $server],
                [$server],
            ],
            'random object becomes key => val' => [
                ['foo' => 'bar', 'baz' => 'qux'],
                [$obj],
            ],
        ];
    };

    /** Passing an array argument raises an exception, since it's not supported. */
    yield function () use ($protocol) {
        $args = [['wat']];
        $e = null;
        try {
            $protocol->parseArgs($args);
        } catch (InvalidArgumentException $e) {
        }
        assert($e instanceof InvalidArgumentException);
        assert($e->getMessage() === 'Not sure what to do about array arguments');
    };
};

