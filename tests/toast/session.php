<?php

use Gentry\Gentry\Wrapper;

return function () : Generator {
    $dog = new Monomelodies\DNode\Dog;
    $session = Wrapper::createObject(Monomelodies\DNode\Session::class, 0, $dog);

    /** @test */
    yield function () use ($session, $dog) {

        $expected = array(
            'method'    => 'methods',
            'arguments' => array($dog),
            'callbacks' => array(
                array(0, 'bark'),
                array(0, 'meow'),
            ),
            'links'     => array(),
        );
        $session->on('request', function ($arg) use (&$expected) {
            $expected = $arg;
        });
        $session->start();
        assert($arg === $expected);
    };

    /** @test */
    yield function () use ($session) {
        $called = false;
        $session->on('end', function ($c) use (&$called) {
            $called = true;
        });
        $session->end();
        assert($called === true);
    };
};

