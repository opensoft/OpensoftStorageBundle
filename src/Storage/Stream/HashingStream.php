<?php

namespace Opensoft\StorageBundle\Storage\Stream;

use GuzzleHttp\Psr7\StreamDecoratorTrait;
use Psr\Http\Message\StreamInterface;

/**
 * Stream decorator that calculates a rolling hash of the stream as it is read.
 *
 * Simplified version inspired by https://github.com/aws/aws-sdk-php/blob/master/src/HashingStream.php
 */
class HashingStream implements StreamInterface
{
    use StreamDecoratorTrait;

    private const HASH_ALGORITHM = 'md5';

    /**
     * @var \HashContext
     */
    private $context;

    /**
     * @var string
     */
    private $hash;

    /**
     * @var int
     */
    private $size;

    /**
     * @var callable
     */
    private $callback;

    /**
     * @param StreamInterface $stream     Stream that is being read.
     * @param callable        $onComplete Function invoked when the
     *                                    hash calculation is completed.
     */
    public function __construct(StreamInterface $stream, callable $onComplete)
    {
        $this->stream = $stream;
        $this->callback = $onComplete;
        $this->size = 0;
    }

    public function read($length)
    {
        $data = $this->stream->read($length);
        if ($this->hash === null) {
            hash_update($this->getContext(), $data);
//            $this->size += length($data);
            if ($this->eof()) {
                $this->hash = hash_final($this->getContext());
                call_user_func($this->callback, $this->hash, $this->size);
            }
        }

        return $data;
    }

    public function seek($offset, $whence = SEEK_SET)
    {
        if ($offset === 0) {
            $this->reset();
            return $this->stream->seek($offset);
        } else {
            // Seeking arbitrarily is not supported.
            return false;
        }
    }

    private function getContext()
    {
        if ($this->context === null) {
            $this->context = hash_init(self::HASH_ALGORITHM);
        }

        return $this->context;
    }

    private function reset()
    {
        $this->hash = $this->context = null;
        $this->size = 0;
    }
}
