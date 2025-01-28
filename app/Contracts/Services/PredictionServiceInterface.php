<?php

namespace SpeechToTextPlugin\Contracts\Services;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Psr\Http\Message\UriInterface;
use SpeechToTextPlugin\Models\Job;

interface PredictionServiceInterface
{
    /**
     * Start a prediction job for speech-to-text processing.
     *
     * @param Job          $job        The job to process
     * @param UriInterface $webhookUri The URI to call when the prediction is complete
     */
    public function startPrediction(Job $job, UriInterface $webhookUri): void;

    /**
     * Process incoming webhook from prediction service.
     *
     * @param Request  $request  The incoming HTTP request
     * @param Response $response The HTTP response
     *
     * @return Response The HTTP response
     */
    public function processWebhook(Request $request, Response $response): Response;
}
