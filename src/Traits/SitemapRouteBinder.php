<?php

namespace Kwaadpepper\RefreshSitemap\Traits;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Kwaadpepper\Enum\BaseEnumRoutable;
use Kwaadpepper\RefreshSitemap\Exceptions\SitemapException;

trait SitemapRouteBinder
{
    /**
     * List of route with its corresponding parameter
     * arguments and models
     *
     * @var \Illuminate\Support\Collection<string,\Illuminate\Support\Collection<string,array<int,string>>>
     */
    private static $routesBinder = [];

    /**
     * Init variables from configuration
     *
     * @return void
     */
    private static function initConfigBinder(): void
    {
        self::$routesBinder = \collect(\config(
            'refresh-sitemap.routesBinder',
            self::$routesBinder
        ))->filter()->mapWithKeys(function ($value, $key) {
            return [
                \strval($key) => \collect($value)->filter()->mapWithKeys(function ($value, $key) {
                    return [
                        \strval($key) => \collect($value)->filter()->map(function ($value) {
                            if (!\is_string($value) and !\is_int($value)) {
                                throw new \Error('Wrong configuration on sitemap routesBinder');
                            }
                            return $value;
                        })->all()
                    ];
                })
            ];
        });
    }

    /**
     * Check if $route exists in routesBinder
     *
     * @param \Illuminate\Routing\Route $route
     * @return boolean
     */
    private static function hasRouteBinder(\Illuminate\Routing\Route $route): bool
    {
        return self::$routesBinder->has($route->getName());
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
            return self::getRouteBinder($route)->has($param);
        }
        return false;
    }

    /**
     * Get the routeBinder
     *
     * @param \Illuminate\Routing\Route $route
     * @return \Illuminate\Support\Collection<string,array<int,string>>
     */
    private static function getRouteBinder(\Illuminate\Routing\Route $route): Collection
    {
        return self::$routesBinder->get($route->getName(), \collect());
    }

    /**
     * Get the routeBinder param
     *
     * @param \Illuminate\Routing\Route $route
     * @param string                    $param
     * @return array<int,string>
     */
    private static function getRouteBinderParam(\Illuminate\Routing\Route $route, string $param): array
    {
        return self::getRouteBinder($route)->get($param, []);
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
                    /** @var object */
                    $rfCls = (new \ReflectionClass($p[0]))->newInstanceWithoutConstructor();
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
