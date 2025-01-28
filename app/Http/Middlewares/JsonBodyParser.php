<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Slim\Exception\HttpBadRequestException;

class JsonBodyParser implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $contentType = $request->getHeaderLine('Content-Type');
        if (str_contains($contentType, 'application/json')) {
            $body = (string) $request->getBody();

            $parsedBody = '' === $body ? [] : json_decode($body, true);
            if (JSON_ERROR_NONE !== json_last_error()) {
                throw new HttpBadRequestException($request, 'Invalid JSON body: ' . json_last_error_msg());
            }
            $request = $request->withParsedBody($parsedBody);
        }

        return $handler->handle($request);
    }
}
