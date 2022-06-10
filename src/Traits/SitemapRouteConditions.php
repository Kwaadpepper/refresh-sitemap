<?php

namespace Kwaadpepper\RefreshSitemap\Traits;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Schema;

trait SitemapRouteConditions
{
    /**
     * List eloquent condition to decide whether
     * a route should appear in the sitemap or not
     *
     * @var array<string,string>
     */
    private static $queryConditions = [];

    /**
     * List of routes that should be ignored
     * when generating sitemap
     *
     * @var array<string>
     */
    private static $ignoreRoutes = [];

    /**
     * Init variables from configuration
     *
     * @return void
     */
    private static function initConfigConditions(): void
    {
        self::$ignoreRoutes    = \config('refresh-sitemap.ignoreRoutes', self::$ignoreRoutes);
        self::$queryConditions = \config('refresh-sitemap.queryConditions', self::$queryConditions);
    }

    /**
     * Add query conditions using the config table
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string                                $modelTable
     * @return void
     */
    private static function queryConditions(Builder &$query, string $modelTable): void
    {
        foreach (self::$queryConditions as $tableColumn => $expectedValue) {
            $query = Schema::hasColumn($modelTable, $tableColumn) ?
                $query->where($tableColumn, $expectedValue) : $query;
        }
    }

    /**
     * Check if the route handles the HTTP GET method
     *
     * @param RoutingRoute $route
     * @return boolean
     */
    private static function routeHandlesGetMethod(RoutingRoute $route): bool
    {
        return in_array('GET', $route->methods());
    }

    /**
     * Check if the actual route uri start with
     * a partial uri from the table
     * Check also if it uses middleware auth
     *
     * @param RoutingRoute $route
     * @return boolean
     */
    private static function routeShouldBeIgnored(RoutingRoute $route): bool
    {
        foreach (self::$ignoreRoutes as $iRoute) {
            if (strpos($route->uri(), ltrim($iRoute, '/')) === 0) {
                return true;
            }
        }
        foreach ($route->middleware() as $middleware) {
            if (strpos($middleware, 'auth') === 0) {
                return true;
            }
        }
        return false;
    }
}
