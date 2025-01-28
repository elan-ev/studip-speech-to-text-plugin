<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Trails\Flash;

/**
 * @SuppressWarnings(StaticAccess)
 */
class SetupFlash implements MiddlewareInterface
{
    /**
     * Handle the incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        Flash::instance();

        return $handler->handle($request);
    }
}
