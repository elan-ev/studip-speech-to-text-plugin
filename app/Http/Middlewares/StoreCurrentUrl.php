<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Routing\Route;
use Slim\Routing\RouteContext;

class StoreCurrentUrl implements MiddlewareInterface
{
    use InteractsWithContentTypes;

    /**
     * Handle the incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $response = $handler->handle($request);

        // $routeContext = RouteContext::fromRequest($request);
        // $route = $routeContext->getRoute();

        if (
            'GET' === $request->getMethod()
            // $route instanceof Route &&
            && !$this->isXmlHttpRequest($request)
            && !$this->detectPrefetchOrPrerender($request)
        ) {
            $_SESSION['_previous.url'] = $request->getRequestTarget();
        }

        return $response;
    }
}
