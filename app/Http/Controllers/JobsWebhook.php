<?php

namespace SpeechToTextPlugin\Http\Controllers;

use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SpeechToTextPlugin\Contracts\Services\PredictionServiceInterface;

/**
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(UnusedFormalParameter)
 */
class JobsWebhook extends JobsController
{
    #[Inject]
    protected PredictionServiceInterface $predictionService;

    public function __invoke(Request $request, Response $response): Response
    {
        return $this->predictionService->processWebhook($request, $response);
    }
}