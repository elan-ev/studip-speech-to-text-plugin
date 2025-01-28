<?php

namespace Studip\Vite;

/**
 * @see Manifest::createTags()
 */
class Tags
{
    public function __construct(
        public readonly array $preload = [],
        public readonly array $css = [],
        public readonly array $js = [],
    ) {
    }
}
