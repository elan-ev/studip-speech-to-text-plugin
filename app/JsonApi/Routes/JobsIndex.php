<?php

namespace SpeechToTextPlugin\JsonApi\Routes;

use SpeechToTextPlugin\Models\Job;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class JobsIndex extends JsonApiController
{
    protected $allowedIncludePaths = [];
    protected $allowedPagingParameters = ['offset', 'limit'];

    public function __invoke(Request $request, Response $response, $args): Response
    {
        $user = $this->getUser($request);
        $resources = collect(Job::findByUser($user));
        $count = $resources->count();

        return $this->getPaginatedContentResponse(
            $resources->slice(...$this->getOffsetAndLimit()),
            $count
        );
    }
}