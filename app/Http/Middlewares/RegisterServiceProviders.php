<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use DI\Container;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use SpeechToTextPlugin\Contracts\Services\PredictionServiceInterface;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;
use SpeechToTextPlugin\Services\ReplicatePredictionService;
use SpeechToTextPlugin\Services\WhisperxApiPredictionService;

class RegisterServiceProviders implements MiddlewareInterface
{
    public function __construct(
        protected Container $container,
        protected LoggerInterface $logger,
    ) {
    }

    /**
     * Handle the incoming request.
     *
     * @SuppressWarnings(Superglobals)
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->container->set(
            RedirectServiceInterface::class,
            $this->container->get(\SpeechToTextPlugin\Services\RedirectService::class)
        );

        $token = $_ENV['REPLICATE_TOKEN'];
        $whisperxApiUrl = $_ENV['WHISPERX_API_URL'];

        $this->container->set(
            PredictionServiceInterface::class,
            new ReplicatePredictionService($token, $this->logger),
            // new WhisperxApiPredictionService($whisperxApiUrl, $this->logger),
        );

        return $handler->handle($request);
    }
}