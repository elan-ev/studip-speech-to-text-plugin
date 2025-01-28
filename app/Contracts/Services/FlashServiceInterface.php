<?php

namespace SpeechToTextPlugin\Contracts\Services;

interface FlashServiceInterface
{
    public function add(string $key, string $message): void;

    public function get(string $key): array;

    public function has(string $key): bool;

    public function clear(): void;

    public function set(string $key, array $messages): void;

    public function all(): array;
}
