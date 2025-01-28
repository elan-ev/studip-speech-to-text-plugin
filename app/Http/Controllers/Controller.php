<?php

namespace SpeechToTextPlugin\Http\Controllers;

use DI\Attribute\Inject;
use Flexi\Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\UriInterface;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;
use Studip\Inertia\Controller as InertiaController;

/**
 * @SuppressWarnings(StaticAccess)
 */
class Controller extends InertiaController
{
    #[Inject]
    protected RedirectServiceInterface $redirectService;

    public function back(): ResponseInterface
    {
        return $this->redirectService->back();
    }

    public function getNamedRoute(string $routeName, array $data = [], array $queryParams = [], bool $absolute = false): UriInterface
    {
        return $this->redirectService->getNamedRoute($routeName, $data, $queryParams, $absolute);
    }

    public function redirectToNamedRoute(
        string $routeName,
        array $data = [],
        array $queryParams = [],
        int $status = 302,
    ): ResponseInterface {
        return $this->redirectService->redirectToNamedRoute($routeName, $data, $queryParams, $status);
    }

    /**
     * @SuppressWarnings(PHPMD.Superglobals)
     */
    public function view(string $view, array $data = []): string
    {
        $factory = new Factory(__DIR__ . '/../../../resources/views');

        return $factory->render($view, $data);
    }
}
