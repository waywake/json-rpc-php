<?php

namespace Tests\Fixtures;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

class ThrowingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    protected StreamInterface $stream;

    public function getContents(): string
    {
        throw new \InvalidArgumentException('stream decode failed');
    }
}
