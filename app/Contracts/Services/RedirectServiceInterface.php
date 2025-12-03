<?php

namespace SpeechToTextPlugin\Contracts\Services;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;

interface RedirectServiceInterface
{
    public function away(string $path, int $status = 302, array $headers = []): ResponseInterface;

    public function back(): ResponseInterface;
    public function getNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        bool $absolute = false,
    ): UriInterface;

    public function redirectToNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        int $status = 302,
    ): ResponseInterface;
}
