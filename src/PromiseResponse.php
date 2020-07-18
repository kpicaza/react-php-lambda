<?php

declare(strict_types=1);

namespace App;

use React\Http\Io\HttpBodyStream;
use React\Promise\PromiseInterface;
use RingCentral\Psr7\Response;

class PromiseResponse extends Response implements PromiseInterface
{
    private PromiseInterface $promise;

    public function __construct(
        PromiseInterface $promise,
        $body = 'php://tmp',
        int $status = 200,
        array $headers = []
    ) {
        if ($body instanceof ReadableStreamInterface && !$body instanceof StreamInterface) {
            $body = new HttpBodyStream($body, null);
        } elseif (!\is_string($body) && !$body instanceof StreamInterface) {
            throw new \InvalidArgumentException('Invalid response body given');
        }

        parent::__construct(
            $status,
            $headers,
            $body
        );
        $this->promise = $promise;
    }

    public function then(
        callable $onFulfilled = null,
        callable $onRejected = null,
        callable $onProgress = null
    ): PromiseInterface {
        return $this->promise->then($onFulfilled, $onRejected, $onProgress);
    }

    public function promise(): PromiseInterface
    {
        return $this->promise;
    }
}
