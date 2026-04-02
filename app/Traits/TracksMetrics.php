<?php

namespace SpeechToTextPlugin\Traits;

use Metrics;

trait TracksMetrics
{
    private const METRICS_BASENAME = "plugins.speechtotextplugin.";

    /**
     * Increment a counter.
     *
     * @param string $stat  the name of the counter
     * @param integer $increment  the amount to increment by; must be within [-2^63, 2^63]
     */
    public static function trackCount(string $stat, int $increment = 1): void
    {
        Metrics::count(self::METRICS_BASENAME . $stat, $increment);
    }

    /**
     * Set a gauge value.
     *
     * @param string $stat  the name of the gauge
     * @param integer $value  the value of the gauge; must be within [0, 2^64]
     */
    public static function trackGauge(string $stat, int $value): void
    {
        Metrics::gauge(self::METRICS_BASENAME . $stat, $value);
    }

    /**
     * Return a timer function that you may invoke to send the
     * recorded time between calling Metrics::startTimer and calling
     * its resulting timer.
     *
     * @param string $stat  the name of the timer
     *
     * @return callable  the timing function
     */
    public static function trackTime(string $stat): callable
    {
        $timer = Metrics::startTimer();

        return fn() => $timer(self::METRICS_BASENAME . $stat);
    }

    /**
     * Record a timing.
     *
     * @param string $stat  the name of the counter
     * @param integer $milliseconds  the amount to milliseconds that something lastedincrement by; must be within [0, 2^64]
     */
    public static function trackTiming(string $stat, int $milliseconds)
    {
        Metrics::timing(self::METRICS_BASENAME . $stat, $milliseconds);
    }
}