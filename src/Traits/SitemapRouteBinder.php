<?php

namespace Kwaadpepper\RefreshSitemap\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Kwaadpepper\Enum\BaseEnumRoutable;
use Kwaadpepper\RefreshSitemap\Exceptions\SitemapException;
use ReflectionClass;

trait SitemapRouteBinder
{
    /**
     * List of route with its corresponding parameter
     * arguments and models
     *
     * @var array<string,array<string,array<int,string>>>
     */
    private static $routesBinder = [];

    /**
     * Init variables from configuration
     *
     * @return void
     */
    private static function initConfigBinder(): void
    {
        self::$routesBinder = \config('refresh-sitemap.routesBinder', self::$routesBinder);
    }

    /**
     * Check if $route exists in routesBinder
     *
     * @param \Illuminate\Routing\Route $route
     * @return boolean
     */
    private static function hasRouteBinder(\Illuminate\Routing\Route $route): bool
    {
        return array_key_exists($route->getName(), self::$routesBinder);
    }

    /**
     * Check if $route exists in routesBinder and has $param
     *
     * @param \Illuminate\Routing\Route $route
     * @param string                    $param
     * @return boolean
     */
    private static function hasRouteBinderParam(\Illuminate\Routing\Route $route, string $param): bool
    {
        if (self::hasRouteBinder($route)) {
            return array_key_exists($param, self::getRouteBinder($route));
        }
        return false;
    }

    /**
     * Get the routeBinder
     *
     * @param \Illuminate\Routing\Route $route
     * @return array
     */
    private static function getRouteBinder(\Illuminate\Routing\Route $route): array
    {
        return self::$routesBinder[$route->getName()] ?? [];
    }

    /**
     * Get the routeBinder param
     *
     * @param \Illuminate\Routing\Route $route
     * @param string                    $param
     * @return array
     */
    private static function getRouteBinderParam(\Illuminate\Routing\Route $route, string $param): array
    {
        return self::$routesBinder[$route->getName()][$param];
    }

    /**
     * Assert that routeBinder is correct
     *
     * @return void
     * @throws SitemapException If a parameter bind is incorrect, or un handled.
     */
    private static function assertRouteBinderIsCorrect(): void
    {
        /** @var \Illuminate\Routing\RouteCollection */
        $routeCollection = Route::getRoutes();
        foreach (self::$routesBinder as $routeName => $params) {
            if (!$routeCollection->hasNamedRoute($routeName)) {
                throw new SitemapException(trans('Route :routeName does not exists', [
                    'routeName' => $routeName
                ]));
            }
            foreach ($params as $pName => $p) {
                if (count($p) != 2) {
                    throw new SitemapException(\trans(
                        // phpcs:ignore Generic.Files.LineLength.TooLong
                        'routesBinder :routeName parameter :parameterName does not have Model classname and parameter name',
                        [
                            'routeName' => $routeName,
                            'parameterName' => $pName
                        ]
                    ));
                }
                try {
                    $rfCls = (new ReflectionClass($p[0]))->newInstanceWithoutConstructor();
                    if (is_subclass_of($rfCls, BaseEnumRoutable::class)) {
                        return;
                    }
                    if (!is_subclass_of($rfCls, Model::class)) {
                        throw new SitemapException(trans(
                            // phpcs:ignore Generic.Files.LineLength.TooLong
                            'Route ":routeName" parameter ":parameterName" first element must be a child of ":className"',
                            [
                                'routeName' => $routeName,
                                'parameterName' => $pName,
                                'className' => Model::class
                            ]
                        ));
                    }
                    if (!Schema::hasColumn($rfCls->getTable(), $p[1])) {
                        throw new SitemapException(\trans(
                            // phpcs:ignore Generic.Files.LineLength.TooLong
                            '":routeName" parameter ":parameterName" second element ":value" must refer to and existing database column of ":column"',
                            [
                                'routeName' => $routeName,
                                'parameterName' => $pName,
                                'value' => $p[1],
                                'column' => $p[0]
                            ]
                        ));
                    }
                } catch (\ReflectionException $e) {
                    throw new SitemapException(trans(
                        'Route ":routeName" parameter ":parameterName" first element must be a child of ":className"',
                        [
                            'routeName' => $routeName,
                            'parameterName' => $pName,
                            'className' => Model::class
                        ]
                    ));
                }//end try
            }//end foreach
        }//end foreach
    }
}
