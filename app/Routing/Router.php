<?php

namespace SpeechToTextPlugin\Routing;

use Slim\Interfaces\RouteCollectorProxyInterface;
use Slim\Routing\RouteCollectorProxy;
use SpeechToTextPlugin\Http\Controllers\JobsDelete;
use SpeechToTextPlugin\Http\Controllers\JobsIndex;
use SpeechToTextPlugin\Http\Controllers\JobsStore;
use SpeechToTextPlugin\Http\Controllers\JobsWebhook;
use SpeechToTextPlugin\Http\Middlewares\HandleInertiaRequests;
use SpeechToTextPlugin\Http\Middlewares\HandleValidation;
use SpeechToTextPlugin\Http\Middlewares\JsonBodyParser;
use SpeechToTextPlugin\Http\Middlewares\RedirectNobody;
use SpeechToTextPlugin\Http\Middlewares\RegisterServiceProviders;
use SpeechToTextPlugin\Http\Middlewares\SetupFlash;
use SpeechToTextPlugin\Http\Middlewares\StoreCurrentUrl;
use Studip\NamedRoutes\NamedRoutes;
use StudIPPlugin;

class Router
{
    public function __construct(private readonly StudIPPlugin $plugin) {
    }

    public function registerRoutes(RouteCollectorProxyInterface $app, string $unconsumedPath): void
    {
        $app->group('/' . strtolower($this->plugin::class), function (RouteCollectorProxy $routes): void {
            self::registerAuthenticatedRoutes($routes);
            self::registerPublicRoutes($routes);
        })
            ->add(JsonBodyParser::class)
            ->add(HandleInertiaRequests::class)
            ->add(NamedRoutes::class)
            ->add(HandleValidation::class)
            ->add(StoreCurrentUrl::class)
            ->add(SetupFlash::class)
            ->add(RegisterServiceProviders::class);
    }

    private static function registerAuthenticatedRoutes(RouteCollectorProxy $routes): void
    {
        $routes->group('', function (RouteCollectorProxy $authRoutes): void {
            $authRoutes->get('', JobsIndex::class)->setName('jobs.index');
            $authRoutes->post('/jobs', JobsStore::class)->setName('jobs.store');
            $authRoutes->delete('/jobs/{id}', JobsDelete::class)->setName('jobs.delete');
        })->add(RedirectNobody::class);
    }

    private static function registerPublicRoutes(RouteCollectorProxy $routes): void
    {
        $routes->post('/webhook', JobsWebhook::class)->setName('jobs.webhook');

        // TODO
        // $routes->get('{path_info:.*}', function ($request, $response, $args) {
        //     dd($args);
        // });
    }
}
