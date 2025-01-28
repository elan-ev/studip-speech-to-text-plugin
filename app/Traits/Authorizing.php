<?php

namespace SpeechToTextPlugin\Traits;

trait Authorizing
{
    /**
     * @param object|class-string $objectOrClass
     */
    public function can(\User $user, string $ability, object|string $objectOrClass, ...$arguments): bool
    {
        if (is_object($objectOrClass)) {
            $class = $objectOrClass::class;

            return $this->retrievePolicy($class)->$ability($user, $objectOrClass, ...$arguments);
        }

        if (class_exists($objectOrClass)) {
            return $this->retrievePolicy($objectOrClass)->$ability($user, ...$arguments);
        }

        throw new \InvalidArgumentException();
    }

    public function cannot(\User $user, string $ability, $objectOrClass, ...$arguments): bool
    {
        return !$this->can($user, $ability, $objectOrClass, ...$arguments);
    }

    private function retrievePolicy(string $class): object
    {
        return $class::getPolicy();
    }
}
