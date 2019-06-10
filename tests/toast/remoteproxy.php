<?php

use Gentry\Gentry\Wrapper;

/** Tests for the remote proxy */
return function () : Generator {
    $proxy = Wrapper::createObject(Monomelodies\DNode\RemoteProxy::class);

    /** Initially, we have no methods */
    yield function () use ($proxy) {
        assert($proxy->getMethods() === []);
    };

    /** We can define a single method */
    yield function () use ($proxy) {
        $foo = function () {};
        $proxy->setMethod('foo', $foo);

        assert($proxy->getMethods() === ['foo' => $foo]);
    };

    /** We can define multiple methods */
    yield function () use ($proxy) {
        $foo = function () {};
        $bar = function () {};

        $proxy->setMethod('foo', $foo);
        $proxy->setMethod('bar', $bar);

        assert(['foo' => $foo, 'bar' => $bar] === $proxy->getMethods());
    };

    /** Defining a method with an existing name overwrites it */
    yield function () {
        $proxy = Wrapper::createObject(Monomelodies\DNode\RemoteProxy::class);
        $foo = function () {};
        $bar = function () {};

        $proxy->setMethod('foo', $foo);
        $proxy->setMethod('foo', $bar);

        assert(['foo' => $bar] === $proxy->getMethods());
    };

    /** We can call defined methods */
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

    /** Calling a non-existing method raises a BadMethodCallException */
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

