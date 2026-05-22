<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Respect\Validation\Exceptions\NestedValidationException;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Factory;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;
use Trails\Flash;

/**
 * @SuppressWarnings(StaticAccess)
 */
class HandleValidation implements MiddlewareInterface
{
    use InteractsWithContentTypes;

    public function __construct(
        private RedirectServiceInterface $redirectService,
        private ResponseFactoryInterface $responseFactory,
    ) {
    }

    /**
     * Handle the incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            Factory::setDefaultInstance(
                (new Factory())->withTranslator('gettext')
            );

            return $handler->handle($request);
        } catch (ValidationException $exception) {
            return $this->expectsJson($request)
                ? $this->jsonResponse(
                    [
                        'errors' => array_values(
                            array_map(
                                fn ($message) => ([
                                    'id' => 'validation',
                                    'status' => 422,
                                    'title' => $message
                                ]),
                                $exception?->getMessages() ?? $exception->getMessage(),
                            )
                        ),
                    ],
                    422
                )
                : $this->redirectBack($exception);
        }
    }

    private function jsonResponse(
        mixed $data = null,
        int $status = 200,
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse($status)->withHeader('Content-Type', 'application/json');

        $json = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (JSON_ERROR_NONE !== json_last_error()) {
            throw new \RuntimeException(json_last_error_msg(), json_last_error());
        }
        $response->getBody()->write($json);

        return $response;
    }

    private function redirectBack(ValidationException $exception): ResponseInterface
    {
        $flash = Flash::instance();
        $flash->set('errors', $exception->getMessages());

        return $this->redirectService->back();
    }
}