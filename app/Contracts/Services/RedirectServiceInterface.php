<?php

namespace SpeechToTextPlugin\Contracts\Services;

use Psr\Http\Message\ResponseInterface;

interface RedirectServiceInterface
{
    public function back(): ResponseInterface;

    public function redirectToNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        int $status = 302,
    ): ResponseInterface;
}
