<?php

namespace SpeechToTextPlugin\JsonApi;

trait Schemas
{
    public function registerSchemas(): array
    {
        return [
            \SpeechToTextPlugin\Models\Job::class => Schemas\Job::class,
        ];
    }
}