<?php

namespace SpeechToTextPlugin\Traits;

trait Policable
{
    public static function getPolicy()
    {
        $reflectionClass = new \ReflectionClass(self::class);
        $class = '\\SpeechToTextPlugin\\Policies\\' . $reflectionClass->getShortName();
        if (!class_exists($class)) {
            throw new \RuntimeException('Could not find Policy class.');
        }

        return new $class();
    }
}
