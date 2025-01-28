<?php

namespace SpeechToTextPlugin\Services;

use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriFactoryInterface;
use Psr\Http\Message\UriInterface;
use Slim\App;
use Slim\Interfaces\RouteParserInterface;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;

class RedirectService implements RedirectServiceInterface
{
    private readonly RouteParserInterface $routeParser;

    public function __construct(
        private readonly App $app,
        private readonly ServerRequestInterface $request,
        private readonly ResponseFactoryInterface $responseFactory,
        private readonly UriFactoryInterface $uriFactory,
    ) {
        $this->routeParser = $this->app->getRouteCollector()->getRouteParser();
    }

    public function away(string $path, int $status = 302, array $headers = []): ResponseInterface
    {
        $response = $this->responseFactory->createResponse($status)->withHeader('Location', $path);

        foreach ($headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }

    public function back(): ResponseInterface
    {
        return $this->responseFactory->createResponse(302)->withHeader('Location', $this->previous());
    }

    public function getNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        bool $absolute = false,
    ): UriInterface {
        $route = $this->uriFactory->createUri($this->routeParser->urlFor($routeName, $data, $queryParams));
        if (!$absolute) {
            return $route;
        }

        return $this->uriFactory
            ->createUri($GLOBALS['ABSOLUTE_URI_STUDIP'])
            ->withPath($route->getPath())
            ->withQuery($route->getQuery())
            ->withFragment($route->getFragment());
    }

    public function redirectToNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        int $status = 302,
    ): ResponseInterface {
        $uri = $this->getNamedRoute($routeName, $data, $queryParams);

        return $this->responseFactory->createResponse($status)->withHeader('Location', (string) $uri);
    }

    /** @SuppressWarnings(Superglobals) */
    private function getPreviousUrlFromSession(): ?string
    {
        return $_SESSION['_previous.url'] ?? null;
    }

    private function isValidUrl(string $path): bool
    {
        if (!preg_match('~^(#|//|https?://|(mailto|tel|sms):)~', $path)) {
            return false !== filter_var($path, FILTER_VALIDATE_URL);
        }

        return true;
    }

    private function previous(?string $fallback = null): string
    {
        $referrer = $this->request->getHeader('Referer');

        $url = $this->request->hasHeader('Referer') ? $this->urlTo($referrer[0]) : $this->getPreviousUrlFromSession();

        if ($url) {
            return $url;
        } elseif ($fallback) {
            return $this->urlTo($fallback);
        }

        return $this->urlTo('/');
    }

    private function urlTo(string $path): string
    {
        if ($this->isValidUrl($path)) {
            return $path;
        }

        $root = $this->app->getBasePath();

        return $root.trim($path, '/');
    }
}
