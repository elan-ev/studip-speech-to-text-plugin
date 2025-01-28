<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use DI\Attribute\Inject;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use SpeechToTextPlugin\Contracts\Services\RedirectServiceInterface;
use Studip\Inertia\Middleware;
use Studip\Inertia\Support\Header;
use Trails\Flash;

/**
 * @SuppressWarnings(StaticAccess)
 */
class HandleInertiaRequests extends Middleware
{
    #[Inject]
    protected RedirectServiceInterface $redirectService;

    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines what to do when an Inertia action returned with no response.
     * By default, we'll redirect the user back to where they came from.
     *
     * @SuppressWarnings(UnusedFormalParameter)
     */
    public function onEmptyResponse(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        return $this->redirectService->back();
    }

    /**
     * Determines what to do when the Inertia asset version has changed.
     * By default, we'll initiate a client-side location visit to force an update.
     *
     * @SuppressWarnings(UnusedFormalParameter)
     */
    public function onVersionChange(ServerRequestInterface $request, ResponseInterface $response): ResponseInterface
    {
        if (PHP_SESSION_ACTIVE === session_status()) {
            $flash = Flash::instance();
            $flash->discard();
        }

        $url = $request->getRequestTarget();

        if ($request->hasHeader(Header::INERTIA)) {
            return $this->responseFactory->createResponse(409)->withHeader(Header::LOCATION, $url);
        }

        return $this->redirectService->away($url);
    }

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     *
     * @SuppressWarnings(UnusedFormalParameter)
     */
    public function version(ServerRequestInterface $request): ?string
    {
        $manifest = dirname(__FILE__, 4).'/dist/.vite/manifest.json';
        if (file_exists($manifest)) {
            return md5_file($manifest);
        }

        return null;
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    #[\Override]
    public function share(ServerRequestInterface $request): array
    {
        return array_merge(parent::share($request), []);
    }
}
