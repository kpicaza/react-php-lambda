<?php

declare(strict_types=1);

namespace App;

use Bref\Context\Context;
use Bref\Context\ContextBuilder;
use Bref\Event\Http\HttpHandler;
use Psr\Http\Message\ResponseInterface;
use React\EventLoop\LoopInterface;
use React\Http\Browser;
use Throwable;

use function Clue\React\Block\await;
use function explode;
use function get_class;
use function getenv;
use function json_decode;
use function json_encode;
use function printf;
use function React\Promise\resolve;
use function sprintf;
use function strlen;
use function strtolower;

use const JSON_THROW_ON_ERROR;
use const PHP_EOL;

class ReactRuntime
{
    private LoopInterface $loop;
    private Browser $client;
    private string $apiUrl;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
        $this->apiUrl = getenv('AWS_LAMBDA_RUNTIME_API');
        $this->client = new Browser($this->loop);
        $this->client->withFollowRedirects(true);
        $this->client->withRejectErrorResponse(true);
        $this->client->withProtocolVersion('1.0');
    }

    public function processNextEvent(HttpHandler $handler, callable $callback): void
    {
        $promise = $this->client->get(
            "http://{$this->apiUrl}/2018-06-01/runtime/invocation/next",
            [
                'Accept' => 'application/json',
                'Content-Length' => 0,
            ]
        )
            ->then(
                static function (ResponseInterface $response) {
                    $contextBuilder = new ContextBuilder();
                    foreach ($response->getHeaders() as $name => $value) {
                        $name = strtolower($name);
                        if ($name === 'lambda-runtime-aws-request-id') {
                            $contextBuilder->setAwsRequestId($value[0]);
                        }
                        if ($name === 'lambda-runtime-deadline-ms') {
                            $contextBuilder->setDeadlineMs((int)$value[0]);
                        }
                        if ($name === 'lambda-runtime-invoked-function-arn') {
                            $contextBuilder->setInvokedFunctionArn($value[0]);
                        }
                        if ($name === 'lambda-runtime-trace-id') {
                            $contextBuilder->setTraceId($value[0]);
                        }
                    }
                    $context = $contextBuilder->buildContext();
                    $event = json_decode($response->getBody()->getContents(), true, 12, JSON_THROW_ON_ERROR);

                    return resolve([$event, $context]);
                },
                function (Throwable $e) {
                    $this->failInitialization('Process Next Event failed.', $e);
                }
            )
            ->then(
                function (array $data) use ($handler) {
                    /** @var Context $context */
                    [$event, $context] = $data;
                    try {
                        if ($context->getAwsRequestId() === '') {
                            throw new \Exception('Failed to determine the Lambda invocation ID');
                        }
                        $jsonData = json_encode($handler->handle($event, $context), JSON_THROW_ON_ERROR);
                        return $this->client->post(
                            sprintf(
                                'http://%s/2018-06-01/runtime/invocation/%s/response',
                                $this->apiUrl,
                                $context->getAwsRequestId()
                            ),
                            [
                                'Content-Type' => 'application/json',
                                'Content-Length: ' . strlen($jsonData),
                            ],
                            $jsonData
                        );
                    } catch (\Throwable $error) {
                        $jsonData = json_encode(
                            [
                                'errorMessage' => $error->getMessage(),
                                'errorType' => get_class($error),
                                'stackTrace' => explode(PHP_EOL, $error->getTraceAsString()),
                            ],
                            JSON_THROW_ON_ERROR
                        );
                        return $this->client->post(
                            sprintf(
                                'http://%s/2018-06-01/runtime/invocation/%s/error',
                                $this->apiUrl,
                                $context->getAwsRequestId()
                            ),
                            [
                                'Content-Type' => 'application/json',
                                'Content-Length: ' . strlen($jsonData),
                            ],
                            $jsonData
                        );
                    }
                }
            )->then(fn() => $this->loop->futureTick($callback));

        resolve($promise);
    }

    public function failInitialization(string $message, ?\Throwable $error = null): void
    {
        // Log the exception in CloudWatch
        echo "$message\n";
        if ($error) {
            if ($error instanceof \Exception) {
                $errorMessage = get_class($error) . ': ' . $error->getMessage();
            } else {
                $errorMessage = $error->getMessage();
            }
            printf(
                "Fatal error: %s in %s:%d\nStack trace:\n%s",
                $errorMessage,
                $error->getFile(),
                $error->getLine(),
                $error->getTraceAsString()
            );
        }

        $this->client->post(
            sprintf(
                'http://%s/2018-06-01/runtime/init/error',
                $this->apiUrl
            ),
            [
                'errorMessage' => $message . ' ' . ($error ? $error->getMessage() : ''),
                'errorType' => $error ? get_class($error) : 'Internal',
                'stackTrace' => $error ? explode(PHP_EOL, $error->getTraceAsString()) : [],
            ]
        );

        $this->loop->futureTick(static function () {});
    }

}
