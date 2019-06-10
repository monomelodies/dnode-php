<?php

namespace Monomelodies\DNode;

class Transformer
{
    public function transform(string $input, callable $callback)
    {
        $callback(strtoupper(preg_replace('/[aeiou]{2,}/', 'oo', $input)));
    }
}

