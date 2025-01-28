<?php

namespace SpeechToTextPlugin\Http\Middlewares;

use Negotiation\Negotiator;
use Psr\Http\Message\ServerRequestInterface;

trait InteractsWithContentTypes
{
    protected function acceptsAnyContentType(ServerRequestInterface $request): bool
    {
        if (!$request->hasHeader('Accept')) {
            return true;
        }

        try {
            $negotiator = new Negotiator();
            $bestMedia = $negotiator->getBest($request->getHeaderLine('Accept'), ['*/*']);

            return null !== $bestMedia;
        } catch (\Exception) {
            return false;
        }
    }

    protected function detectPrefetchOrPrerender(ServerRequestInterface $request): string
    {
        if ($request->hasHeader('X-Moz') && 'prefetch' === strtolower($request->getHeader('X-Moz')[0])) {
            return true;
        }

        if ($request->hasHeader('X-Purpose') && 'preview' === strtolower($request->getHeader('X-Purpose')[0])) {
            return true;
        }

        if (
            $request->hasHeader('Purpose')
            && in_array('prefetch', array_map(strtolower(...), $request->getHeader('Purpose')))
        ) {
            return true;
        }

        return false;
    }

    protected function expectsJson(ServerRequestInterface $request): bool
    {
        return ($this->isXmlHttpRequest($request) && $this->acceptsAnyContentType($request))
            || $this->wantsJson($request);
    }

    protected function isXmlHttpRequest(ServerRequestInterface $request): bool
    {
        return $request->hasHeader('X-Requested-With')
            && 'XMLHttpRequest' === $request->getHeader('X-Requested-With')[0];
    }

    protected function wantsJson(ServerRequestInterface $request): bool
    {
        try {
            $negotiator = new Negotiator();
            $bestMedia = $negotiator->getBest($request->getHeaderLine('Accept'), ['application/json']);

            return null !== $bestMedia;
        } catch (\Exception) {
            return false;
        }
    }
}
