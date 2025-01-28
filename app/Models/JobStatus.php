<?php

namespace SpeechToTextPlugin\Models;

enum JobStatus
{
    case starting;
    case processing;
    case succeeded;
    case failed;
    case canceled;

    public static function names(): array
    {
        return array_map(fn($enum) => $enum->name, static::cases());
    }
}
