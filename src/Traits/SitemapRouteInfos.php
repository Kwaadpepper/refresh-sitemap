<?php

namespace Kwaadpepper\RefreshSitemap\Traits;

use Spatie\Sitemap\Tags\Url;

trait SitemapRouteInfos
{
    /**
     * Route priority
     *
     * @var float
     */
    private static $routeDefaultPriority = 0.5;

    /**
     * Route de fault frequency
     *
     * @var string
     */
    private static $routeDefaultFrequency = Url::CHANGE_FREQUENCY_DAILY;

    /**
     * Route priorities
     *
     * @var array<string,float>
     */
    private static $routePriorities = [];

    /**
     * Route frequencies
     *
     * @var array<string,string>
     */
    private static $routeFrequencies = [];

    /**
     * Available route frequencies
     *
     * @var array<string>
     */
    private static $existingRoutefreqencies = [
        Url::CHANGE_FREQUENCY_ALWAYS,
        Url::CHANGE_FREQUENCY_HOURLY,
        Url::CHANGE_FREQUENCY_DAILY,
        Url::CHANGE_FREQUENCY_WEEKLY,
        Url::CHANGE_FREQUENCY_MONTHLY,
        Url::CHANGE_FREQUENCY_YEARLY,
        Url::CHANGE_FREQUENCY_NEVER
    ];

    /**
     * Init variables from configuration
     *
     * @return void
     */
    private static function initConfigInfo(): void
    {
        self::$routeDefaultPriority  = \config('refresh-sitemap.routeDefaultPriority', self::$routeDefaultPriority);
        self::$routeDefaultFrequency = \config('refresh-sitemap.routeDefaultFrequency', self::$routeDefaultFrequency);
        self::$routePriorities       = \config('refresh-sitemap.routePriorities', self::$routePriorities);
        self::$routeFrequencies      = \config('refresh-sitemap.routeFrequencies', self::$routeFrequencies);
    }

    /**
     * Get a route priority
     * @param string $routeName
     * @return string
     */
    private static function getRouteFrequency(string $routeName): string
    {
        foreach (self::$routeFrequencies as $rName => $rFreqency) {
            if (\strpos($routeName, $rName) === 0) {
                return \in_array($rFreqency, self::$existingRoutefreqencies) ?
                    $rFreqency : self::$routeDefaultFrequency;
            }
        }
        return self::$routeDefaultFrequency;
    }

    /**
     * Get a route priority
     * @param string $routeName
     * @return float
     */
    private static function getRoutePriority(string $routeName): float
    {
        foreach (self::$routePriorities as $rName => $rPriority) {
            if (\strpos($routeName, $rName) === 0) {
                // Is a float between 0 and 1 ? $rPriority : $routeDefaultPriority .
                $rPrio = \is_numeric($rPriority) ? \floatval($rPriority) : self::$routeDefaultPriority;
                return (($rPrio <=> 0) === 1 and ($rPrio <=> 1) === -1) ? $rPrio : self::$routeDefaultPriority;
            }
        }
        return self::$routeDefaultPriority;
    }
}
