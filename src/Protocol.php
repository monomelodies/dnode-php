<?php

namespace Monomelodies\DNode;

use React\Socket\{ ServerInterface, ConnectionInterface };

class Protocol
{
    /** @var object */
    private $wrapper;
    /** @var array */
    private $sessions = [];
    /** @var React\Socket\ConnectionInterface */
    private $connection;

    public function __construct(object $wrapper)
    {
        $this->wrapper = $wrapper;
    }

    public function create()
    {
        // FIXME: Random ID generation, should be unique
        $id = microtime();
        $session = new Session($id, $this->wrapper);

        $that = $this;
        $session->on('end', function () use ($id) {
            return $this->destroy($id);
        });

        $this->sessions[$id] = $session;

        return $session;
    }

    public function destroy($id)
    {
        unset($this->sessions[$id]);
    }

    public function end()
    {
        foreach ($this->sessions as $id => $session) {
            $this->sessions[$id]->end();
        }
    }

    public function parseArgs($args)
    {
        $params = [];

        foreach ($args as $arg) {
            if (is_string($arg)) {
                if (preg_match('/^\d+$/', $arg)) {
                    $params['port'] = $arg;
                    continue;
                }
                if (preg_match('/^\\//', $arg)) {
                    $params['path'] = $arg;
                    continue;
                }
                $params['host'] = $arg;
                continue;
            }

            if (is_numeric($arg)) {
                $params['port'] = $arg;
                continue;
            }

            if (is_object($arg)) {
                if ($arg instanceof \Closure) {
                    $params['block'] = $arg;
                    continue;
                }

                if ($arg instanceof ServerInterface) {
                    $params['server'] = $arg;
                    continue;
                }

                foreach ($arg as $key => $value) {
                    $params[$key] = $value;
                }
                continue;
            }

            throw new \InvalidArgumentException("Not sure what to do about " . gettype($arg) . " arguments");
        }

        return $params;
    }
}

