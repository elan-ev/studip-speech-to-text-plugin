<?php

namespace SpeechToTextPlugin\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use Slim\Exception\HttpUnauthorizedException;
use SpeechToTextPlugin\Models\Job;

/**
 * @SuppressWarnings(StaticAccess)
 */
class JobsDelete extends Controller
{
    /**
     * @SuppressWarnings(UnusedFormalParameter)
     */
    public function __invoke(Request $request, Response $response, array $args): Response
    {
        $job = Job::find((int) $args['id']);
        $user = \User::findCurrent();
        if (!$job || $this->cannot($user, 'delete', $job)) {
            throw new HttpUnauthorizedException($request);
        }

        $job->delete();

        return $this->accepted();
    }
}