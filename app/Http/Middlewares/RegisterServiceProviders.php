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

        $replicateToken = $_ENV['REPLICATE_TOKEN'] ?? null;
        $hfToken = $_ENV['HF_TOKEN'] ?? null;
        $whisperxApiUrl = $_ENV['WHISPERX_API_URL'] ?? null;

        if ($replicateToken) {
            $this->container->set(
                PredictionServiceInterface::class,
                new ReplicatePredictionService($replicateToken, $hfToken, $this->logger),
            );
        } elseif ($whisperxApiUrl) {
            $this->container->set(
                PredictionServiceInterface::class,
                new WhisperxApiPredictionService($whisperxApiUrl, $this->logger),
            );
        } else {
            throw new \RuntimeException('No PredictionService has been configured. See README.org.');
        }

        return $handler->handle($request);
    }
}