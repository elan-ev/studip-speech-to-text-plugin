<?php

namespace SpeechToTextPlugin\Traits;

trait LogsErrors
{
    /**
     * Logs an informational message.
     *
     * @param string $message    The log message format
     * @param mixed  ...$context Additional context to log
     */
    protected function logInfo(string $message, ...$context): void
    {
        $this->logger->info(sprintf('%s: '.$message, \SpeechToTextPlugin::class, ...$context));
    }

    /**
     * Logs a warning message.
     *
     * @param string $message    The log message format
     * @param mixed  ...$context Additional context to log
     */
    protected function logWarning(string $message, ...$context): void
    {
        $this->logger->warning(sprintf('%s: '.$message, \SpeechToTextPlugin::class, ...$context));
    }

    /**
     * Logs an error message.
     *
     * @param string $message    The log message format
     * @param mixed  ...$context Additional context to log
     */
    protected function logError(string $message, ...$context): void
    {
        $this->logger->error(sprintf('%s: '.$message, \SpeechToTextPlugin::class, ...$context));
    }
}
