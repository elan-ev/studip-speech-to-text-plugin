<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Request;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;

/**
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(Superglobals)
 */
class RedirectNobody implements MiddlewareInterface
{
    public function __construct(
        private readonly RedirectServiceInterface $redirectService,
    ) {
    }

    /**
     * Handle the incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        if (!\User::findCurrent()?->id) {
            $_SESSION['redirect_after_login'] = \Request::url();

            return $this->redirectService->away(\URLHelper::getURL('dispatch.php/login'));
        }

        return $handler->handle($request);
    }
}
