<?php

declare(strict_types=1);

namespace App\Application\Http\Handler;

use App\PromiseResponse;
use App\Application\Event\SomeEvent;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Laminas\Diactoros\Response\JsonResponse;

use function React\Promise\resolve;

class HomePage implements RequestHandlerInterface
{
    /** @var EventDispatcherInterface */
    private $eventDispatcher;

    public function __construct(EventDispatcherInterface $eventDispatcher)
    {
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new PromiseResponse(resolve($this->eventDispatcher->dispatch(SomeEvent::occur()))
            ->then(fn () => new JsonResponse([
                'docs' => 'https://antidotfw.io',
                'Message' => 'Welcome to Antidot Framework Starter'
            ]))
        );
    }
}
