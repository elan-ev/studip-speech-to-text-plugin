<?php

namespace SpeechToTextPlugin\Http\Controllers;

use DI\Attribute\Inject;
use Flexi\Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;
use SpeechToTextPlugin\Traits\Authorizing;

class Controller
{
    use Authorizing;

    #[Inject]
    protected RedirectServiceInterface $redirectService;

    #[Inject]
    protected ResponseFactoryInterface $responseFactory;

    public function back(): ResponseInterface
    {
        return $this->redirectService->back();
    }

    public function getNamedRoute(string $routeName, array $data = [], array $queryParams = [], bool $absolute = false): UriInterface
    {
        return $this->redirectService->getNamedRoute($routeName, $data, $queryParams, $absolute);
    }

    public function redirectToNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        int $status = 302,
    ): ResponseInterface {
        return $this->redirectService->redirectToNamedRoute($routeName, $data, $queryParams, $status);
    }

    public function view(string $view, array $data = []): string
    {
        $factory = new Factory(__DIR__ . '/../../../resources/views');

        return $factory->render($view, $data);
    }

    public function ok(string $body = ''): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();
        $response->getBody()->write($body);

        return $response;
    }

    public function accepted(): ResponseInterface
    {
        $response = $this->responseFactory->createResponse();

        return $response->withStatus(202);
    }

    public function jsonResponse(
        ?array $data = null,
        int $status = 200,
        int $encodingOptions = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse();

        if (isset($data)) {
            $payload = json_encode($data, $encodingOptions);
            $response->getBody()->write($payload);
        }

        return $response
            ->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withStatus($status);
    }
}