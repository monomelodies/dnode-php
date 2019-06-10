<?php

use Gentry\Gentry\Wrapper;

return function () : Generator {
    $protocol = Wrapper::createObject(Monomelodies\DNode\Protocol::class, new Monomelodies\DNode\Dog);

    /**
     * @test
     * @covers DNode\Protocol::__construct
     * @covers DNode\Protocol::create
     */
    yield function () use ($protocol) {
        $session = $protocol->create();
        $this->assertInstanceOf('DNode\Session', $session);
    };

    /**
     * @test
     * @covers DNode\Protocol::destroy
     */
    yield function () use ($protocol) {
        $session = $protocol->create();
        $protocol->destroy($session->id);
    };

    /**
     * @test
     * @covers DNode\Protocol::end
     */
    yield function () use ($protocol) {
        $sessions = array(
            $protocol->create(),
            $protocol->create(),
        );

        foreach ($sessions as $session) {
            $session->on('end', $this->expectCallableOnce());
        }

        $protocol->end();
    };

    /**
     * @test
     * @covers DNode\Protocol::parseArgs
     * @dataProvider provideParseArgs
     */
    yield function () use ($protocol) {
        $this->assertSame($expected, $protocol->parseArgs($args));
    };

    yield function () use ($protocol) {
        $closure = function () {};
        $server = new Monomelodies\DNode\ServerStub;

        $obj = new \stdClass();
        $obj->foo = 'bar';
        $obj->baz = 'qux';

        return array(
            'string number becomes port' => array(
                array('port' => '8080'),
                array('8080'),
            ),
            'leading / becomes path' => array(
                array('path' => '/foo'),
                array('/foo'),
            ),
            'string becomes host' => array(
                array('host' => 'foo'),
                array('foo'),
            ),
            'integer becomes port' => array(
                array('port' => 8080),
                array(8080),
            ),
            'Closure becomes block' => array(
                array('block' => $closure),
                array($closure),
            ),
            'ServerInterface becomes server' => array(
                array('server' => $server),
                array($server),
            ),
            'random object becomes key => val' => array(
                array('foo' => 'bar', 'baz' => 'qux'),
                array($obj),
            ),
        );
    };

    /**
     * @test
     * @covers DNode\Protocol::parseArgs
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage Not sure what to do about array arguments
     */
    yield function () use ($protocol) {
        $args = array(array('wat'));
        $protocol->parseArgs($args);
    };
};

