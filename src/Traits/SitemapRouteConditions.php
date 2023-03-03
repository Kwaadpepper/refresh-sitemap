<?php

namespace Kwaadpepper\RefreshSitemap\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Schema;

trait SitemapRouteConditions
{
    /**
     * List eloquent condition to decide whether
     * a route should appear in the sitemap or not
     *
     * @var \Illuminate\Support\Collection<string,string|boolean>
     */
    private static $queryConditions = [];

    /**
     * List of routes that should be ignored
     * when generating sitemap
     *
     * @var \Illuminate\Support\Collection<string>
     */
    private static $ignoreRoutes = [];

    /**
     * Init variables from configuration
     *
     * @return void
     */
    private static function initConfigConditions(): void
    {
        self::$ignoreRoutes    = \collect(\config('refresh-sitemap.ignoreRoutes', self::$ignoreRoutes))
            ->map(function ($value) {
                if (!\is_string($value)) {
                    throw new \Error('ignoreRoutes can take only string with route name or url');
                }
                return $value;
            });
        self::$queryConditions = \collect(\config('refresh-sitemap.queryConditions', self::$queryConditions))
        ->mapWithKeys(function ($value, $index) {
            if (!\is_string($index) or !(\is_string($value) || \is_bool($value))) {
                throw new \Error(
                    'queryConditions can take only and array with array<string,string|boolean>'
                );
            }
            return [
                $index => $value
            ];
        });
    }

    /**
     * Add query conditions using the config table
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $modelTable
     * @return void
     */
    private static function queryConditions(Builder $query, string $modelTable): void
    {
        foreach (self::$queryConditions as $tableColumn => $expectedValue) {
            if (Schema::hasColumn($modelTable, $tableColumn)) {
                $query->where($tableColumn, $expectedValue);
            }
        }
    }

    /**
     * Check if the route handles the HTTP GET method
     *
     * @param \Illuminate\Routing\Route $route
     * @return boolean
     */
    private static function routeHandlesGetMethod(\Illuminate\Routing\Route $route): bool
    {
        return in_array('GET', $route->methods());
    }

    /**
     * Check if the actual route uri start with
     * a partial uri from the table
     * Check also if it uses middleware auth
     *
     * ! Routes with auth middleware will be automatically ignored.
     *
     * @param \Illuminate\Routing\Route $route
     * @return boolean
     */
    private static function routeShouldBeIgnored(\Illuminate\Routing\Route $route): bool
    {
        return self::$ignoreRoutes->reduce(function ($carry, $iRoute) use ($route) {
            // * Check match route url or name
            /** @var boolean|null $carry */
            /** @var string $iRoute Can be route url or route name */
            return $carry or (strpos($route->uri(), ltrim($iRoute, '/')) === 0) or
            (strpos(\strval($route->getName()), $iRoute) === 0);
        }) || collect($route->middleware())->reduce(function ($carry, $middleware) {
            // * Check route does not have auth middleware
            /** @var boolean|null $carry */
            /** @var string $middleware */
            return $carry or (strpos(\strval($middleware), 'auth') === 0);
        });
    }
}
