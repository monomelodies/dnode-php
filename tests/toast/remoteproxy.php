<?php

use Gentry\Gentry\Wrapper;

return function () : Generator {
    $proxy = Wrapper::createObject(Monomelodies\DNode\RemoteProxy::class);

    /** @test */
    yield function () use ($proxy) {
        assert($proxy->getMethods() === []);
    };

    /** @test */
    yield function () use ($proxy) {
        $foo = function () {};
        $proxy->setMethod('foo', $foo);

        assert($proxy->getMethods() === ['foo' => $foo]);
    };

    /** @test */
    yield function () use ($proxy) {
        $foo = function () {};
        $bar = function () {};

        $proxy->setMethod('foo', $foo);
        $proxy->setMethod('bar', $bar);

        assert(['foo' => $foo, 'bar' => $bar] === $proxy->getMethods());
    };

    /** @test */
    yield function () use ($proxy) {
        $foo = function () {};
        $bar = function () {};

        $proxy->setMethod('foo', $foo);
        $proxy->setMethod('foo', $bar);

        assert(['foo' => $bar] === $proxy->getMethods());
    };

    /** @test */
    yield function () use ($proxy) {
        $foo = function ($arg) use (&$fooCalled) {
            $fooCalled = $arg;
        };
        $bar = function ($arg) use (&$barCalled) {
            $barCalled = $arg;
        };

        $proxy->setMethod('foo', $foo);
        $proxy->setMethod('bar', $bar);

        $proxy->foo('a');
        $proxy->bar('b');

        assert($fooCalled === 'a');
        assert($barCalled === 'b');
    };

    /**
     * @test
     * @expectedException BadMethodCallException
     * @expectedExceptionMessage Method baz not available
     */
    yield function () use ($proxy) {
        $e = null;
        try {
            $proxy->baz();
        } catch (BadMethodCallException $e) {
        }
        assert($e instanceof BadMethodCallException);
        assert($e->getMessage() === 'Method baz not available');
    };
};

