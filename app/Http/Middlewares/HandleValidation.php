<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Respect\Validation\Exceptions\ValidationException;
use Respect\Validation\Factory;
use Respect\Validation\Message\ParameterStringifier;
use Respect\Validation\Message\Stringifier\KeepOriginalStringName;
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
        private \StudIPPlugin $plugin,
    ) {
    }

    /**
     * Handle the incoming request.
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        try {
            Factory::setDefaultInstance(
                (new Factory())
                    ->withTranslator($this->translate(...))
                    ->withParameterStringifier(new readonly class (new KeepOriginalStringName()) implements ParameterStringifier {
                        public function __construct(private ParameterStringifier $stringifier)
                        {
                        }

                        public function stringify(string $name, $value): string
                        {
                            return 'name' === $name ? '{{' . $name . '}}' : $this->stringifier->stringify($name, $value);
                        }
                    })
            );

            return $handler->handle($request);
        } catch (ValidationException $exception) {
            return $this->expectsJson($request)
                ? $this->jsonResponse(422, [
                    'message' => $exception->getMessage(),
                    'errors' => $exception->getMessages(),
                ])
                : $this->redirectBack($exception);
        }
    }

    private function jsonResponse(
        mixed $data = null,
        int $status = 200,
    ): ResponseInterface {
        $response = $this->responseFactory->createResponse($status)->withHeader('Content-Type', 'application/json');

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
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

    /**
     * @SuppressWarnings(Superglobals)
     */
    private function translate(string $string): string
    {
        if ('de_DE' !== $_SESSION['_language']) {
            return $string;
        }

        $translation = match ($string) {
            '{{name}} must not be empty' => '{{name}} darf nicht leer sein',
            '{{name}} must be valid' => '{{name}} muss gültig sein',
            'All of the required rules must pass for {{name}}' => 'Alle erforderlichen Regeln müssen für {{name}} erfüllt sein',
            'These rules must pass for {{name}}' => 'Diese Regeln müssen für {{name}} erfüllt sein',
            default => null,
        };

        if (!$translation) {
            dd($string);
        }

        return $translation;
    }
}