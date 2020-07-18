<?php

declare(strict_types=1);

namespace App;

use Bref\Context\Context;
use Bref\Event\Http\HttpRequestEvent;
use Bref\Event\Http\HttpResponse;
use Bref\Event\Http\Psr7Bridge;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;

use function Clue\React\Block\await;
use function React\Promise\resolve;

class ReactHandler
{
    private RequestHandlerInterface $handler;
    private LoopInterface $loop;

    public function __construct(LoopInterface $loop, RequestHandlerInterface $handler)
    {
        $this->handler = $handler;
        $this->loop = $loop;
    }

    public function handleRequest(HttpRequestEvent $event, Context $context): PromiseInterface
    {
        return resolve(Psr7Bridge::convertRequest($event, $context))
            ->then(fn(ServerRequestInterface $request) => $this->handler->handle($request))
            ->then(function(ResponseInterface $response) {
                if ($response instanceof PromiseInterface) {
                    return $response->then(function (HttpResponse $response) {
                        return Psr7Bridge::convertResponse($response);
                    });
                }

                return Psr7Bridge::convertResponse($response);
            });
    }

    public function handle($event, Context $context): PromiseInterface
    {
        // See https://bref.sh/docs/runtimes/http.html#cold-starts
        if (isset($event['warmer']) && $event['warmer'] === true) {
            return resolve(['Lambda is warm']);
        }

        $httpEvent = new HttpRequestEvent($event);
        return resolve($httpEvent)
            ->then(fn(HttpRequestEvent $httpEvent) => $this->handleRequest($httpEvent, $context))
            ->then(function ($response) use ($httpEvent) {
                return $response->toApiGatewayFormat($httpEvent->hasMultiHeader());
            }
    );
    }
}
