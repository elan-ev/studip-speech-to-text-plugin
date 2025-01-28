<?php

namespace SpeechToTextPlugin\Http\Controllers;

use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use SpeechToTextPlugin\Models\Job;
use SpeechToTextPlugin\Models\UserUpload;

/**
 * @SuppressWarnings(StaticAccess)
 * @SuppressWarnings(UnusedFormalParameter)
 */
class JobsIndex extends JobsController
{
    public function __invoke(Request $request, Response $response): Response
    {
        \Navigation::activateItem('/contents/speech-to-text/index');

        $user = \User::findCurrent();
        $jobs = collect(Job::findByUser($user))->map($this->transformJobToResource(...));

        return $this->inertia(
            'Jobs/Index',
            [
                'jobs' => $jobs,
                'usage' => UserUpload::getUsage($user),
                'MAX_UPLOAD' => 1024 * 1024 * 1024 * 1,
                'QUOTA' => 1024 * 1024 * 1024 * 5,
            ]
        );
    }
}
