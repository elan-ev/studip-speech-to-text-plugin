<?php

namespace SpeechToTextPlugin\JsonApi;

trait Routes
{
    public function registerAuthenticatedRoutes(\Slim\Routing\RouteCollectorProxy $group): void
    {
        $group->get('/speechtotext-jobs', Routes\JobsIndex::class);
    }

    public function registerUnauthenticatedRoutes(\Slim\Routing\RouteCollectorProxy $group)
    {
    }
}