<?php

use Gentry\Gentry\Wrapper;

return function () : Generator {
    $dog = new Monomelodies\DNode\Dog;
    $session = Wrapper::createObject(Monomelodies\DNode\Session::class, 0, $dog);

    /** Starting a session issues the 'methods' request */
    yield function () use ($session, $dog) {

        $expected = [
            'method'    => 'methods',
            'arguments' => [$dog],
            'callbacks' => [
                [0, 'bark'],
                [0, 'meow'],
            ],
            'links'     => [],
        ];
        $actual = null;
        $session->on('request', function ($arg) use (&$actual) {
            $actual = $arg;
        });
        $session->start();
        assert($actual === $expected);
    };

    /** Ending a session calls the callback */
    yield function () use ($session) {
        $called = false;
        $session->on('end', function () use (&$called) {
            $called = true;
        });
        $session->end();
        assert($called === true);
    };
};

