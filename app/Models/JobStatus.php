<?php

namespace SpeechToTextPlugin\Models;

enum JobStatus
{
    case starting;
    case processing;
    case succeeded;
    case failed;
    case canceled;

    /**
     * This function returns an array of all possible names for the JobStatus enum.
     *
     * @return array An array of strings representing the names of the JobStatus enum cases.
     */
    public static function names(): array
    {
        return array_map(fn($enum) => $enum->name, static::cases());
    }
}
