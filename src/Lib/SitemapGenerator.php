<?php

namespace Kwaadpepper\RefreshSitemap\Lib;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Kwaadpepper\Enum\BaseEnumRoutable;
use Kwaadpepper\RefreshSitemap\Exceptions\SitemapException;
use Kwaadpepper\RefreshSitemap\Traits\SitemapRouteBinder;
use Kwaadpepper\RefreshSitemap\Traits\SitemapRouteConditions;
use Kwaadpepper\RefreshSitemap\Traits\SitemapRouteInfos;
use ReflectionClass;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

final class SitemapGenerator
{

    use SitemapRouteBinder;
    use SitemapRouteConditions;
    use SitemapRouteInfos;

    /** @var \Spatie\Sitemap\Sitemap */
    private $siteMap = null;

    public function __construct()
    {
        $this->siteMap = Sitemap::create();
    }

    /**
     * Generate the sitemap Xml
     *
     * @return string An xml file
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException
     */
    public function generateSiteMap(): string
    {
        $this->generate();
        return $this->siteMap->render();
    }

    /**
     * Write sitemap into a file
     *
     * @param string $path
     * @return \Spatie\Sitemap\Sitemap
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    public function writeToFile(string $path): Sitemap
    {
        $this->generate();
        return $this->siteMap->writeToFile($path);
    }

    // --- PRIVATE METHODS ---

    /**
     * Actually generate the sitemap
     *
     * @return void
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException
     */
    private function generate(): void
    {
        self::initConfig();
        $this->assertRouteBinderIsCorrect();

        $routeCollection = Route::getRoutes();
        $generatedUris = [];

        /** @var \Illuminate\Routing\Route $route */
        foreach ($routeCollection as $route) {
            if (self::routeShouldBeIgnored($route)) {
                continue;
            }

            if (!self::routeHandlesGetMethod($route)) {
                continue;
            }

            $this->handleRoute($route, $generatedUris);
        }

        foreach ($generatedUris as $smRules) {
            $this->siteMap->add(Url::create($smRules[0])
                ->setLastModificationDate(Carbon::yesterday())
                ->setChangeFrequency($smRules[1])
                ->setPriority($smRules[2]));
        }
    }

    /**
     * Init variables from configuration
     */
    private static function initConfig(): void
    {
        self::initConfigBinder();
        self::initConfigConditions();
        self::initConfigInfo();
    }

    private function handleRoute(RoutingRoute $route, array &$generatedUris)
    {
        /** @var array to be populated with models from DB to generate a route */
        $rParams = [];

        /** @var \array [lluminate\Routing\Route::signatureParameters] parameters signature from the controller */
        $params = $route->signatureParameters();
        /** @var \array [Illuminate\Routing\Route::parameterNames] parameters name from the registered route */
        $pNames = $route->parameterNames();

        $hasModels = false;

        $this->handleParams($params, $pNames, $route, $rParams, $hasModels);

        // If the route has no dynamic models as parameters
        if (!count($params) or !$hasModels) {
            $rName = $route->getName();
            $generatedUris[] = [
                route($route->getName()),
                self::getRouteFrequency($rName),
                self::getRoutePriority($rName)
            ];
        } else { // If the route has dynamic models as parameters
            $this->genRoute($route, $generatedUris, $rParams);
        }
    }

    /**
     * Handle all route params
     *
     * @param array $params
     * @param array|null $pNames
     * @param \Illuminate\Routing\Route $route
     * @param array $rParams
     * @param bool $hasModels
     * @return void
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \ReflectionException
     * @throws \InvalidArgumentException
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException
     */
    private function handleParams(
        array $params,
        array $pNames = null,
        RoutingRoute $route,
        array &$rParams,
        bool &$hasModels
    ) {
        foreach ($params as $param) {

            /** @var ReflectionClass|null $name */
            $name = $param->getType() && !$param->getType()->isBuiltin()
                ? new ReflectionClass($param->getType()->getName())
                : null;

            /** @var string $pName $parameter name on route uri */
            $pName = '';

            // If the route has parameters and the controller accepts it
            if (count($pNames) and \array_key_exists($param->getPosition(), $pNames)) {
                // Get the controller parameter name for the route
                $pName = $pNames[$param->getPosition()];
            }

            if (config('app.debug')) {
                dump(\trans(
                    'Route name : :routeName, Param [type: :typeName, name: :paramName, binder: :hasBinder]',
                    [
                        'routeName' => $route->getName(),
                        'typeName' => $name ? $name->getName() : '',
                        'paramName' => $pName,
                        'hasBinder' => $this->hasRouteBinderParam($route, $pName) ? 'true' : 'false'
                    ]
                ));
            }

            // If We have a custom binder for this route
            if ($this->hasRouteBinderParam($route, $pName)) {
                $hasModels = true;
                $this->processWithRouteBinderParam($rParams, $route, $pName);
                continue;
            }

            // If This route takes a model as param
            if ($name and $name->isSubclassOf(Model::class)) {
                $hasModels = true;
                /** @var \string */
                $modelClassName = $name->getName();
                $this->setParamsForModel($rParams, $modelClassName, $pName);
                continue;
            }
        }
    }

    /**
     * Process route with binderParam
     *
     * @param RoutingRoute $route
     * @param string $pName
     * @return void
     */
    private function processWithRouteBinderParam(array &$rParams, RoutingRoute $route, string $pName)
    {
        $o = $this->getRouteBinderParam($route, $pName);

        /** @var \string */
        $modelClassName = $o[0];
        /** @var \string  */
        $routeParamName = $o[1];
        $rfLCls = (new ReflectionClass($modelClassName));

        switch (true) {
            case $rfLCls->isSubclassOf(Model::class):
                $this->setParamsForModel($rParams, $modelClassName, $pName, $routeParamName);
                break;
            case $rfLCls->isSubclassOf(BaseEnumRoutable::class):
                $this->setParamForEnum($rParams, $rfLCls->getName(), $pName);
                break;
            default:
                throw new SitemapException(trans('Unhandled class type :className', [
                    'className' => $rfLCls->getName()
                ]));
        }
    }

    /**
     * Set route param Array for Enums
     *
     * @param array $rParams
     * @param string $enumClassName
     * @param string $pName
     * @return void
     */
    private function setParamForEnum(array &$rParams, string $enumClassName, string $pName)
    {
        foreach (forward_static_call(sprintf('%s::toArray', $enumClassName)) as $enum) {
            if (!array_key_exists($pName, $rParams)) {
                $rParams[$pName] = [];
            }
            $rParams[$pName][] = $enum->value;
        }
    }

    /**
     * Set route param Array for Models
     *
     * @param array $rParams
     * @param string $modelClassName
     * @param string $pName
     * @param string $routeParamName
     * @return void
     */
    private function setParamsForModel(
        array &$rParams,
        string $modelClassName,
        string $pName,
        string $routeParamName = null
    ) {
        $i = 0;
        $limit = 10;
        /** @var string $modelTable */
        $modelTable = (new ReflectionClass($modelClassName))->newInstanceWithoutConstructor()->getTable();
        $q = $modelClassName::query();
        self::queryConditions($q, $modelTable);
        do {
            $results = $q->offset($i * $limit)->limit($limit)->get();
            $length = count($results);
            $results->map(function (Model $model) use (&$rParams, $pName, $routeParamName) {
                if (!array_key_exists($pName, $rParams)) {
                    $rParams[$pName] = [];
                }
                $rParams[$pName][] = $routeParamName ? $model[$routeParamName] : $model;
            });
            $i++;
        } while ($length);
    }

    /**
     * Recursively generate routes for all params
     *
     * @param RoutingRoute $route
     * @param array $generatedUris
     * @param array $params
     * @return void
     */
    private function genRoute(RoutingRoute $route, array &$generatedUris, array $params)
    {
        $filtered = false;
        foreach ($params as $pName => $param) {
            if (is_array($param)) {
                collect($param)->map(function ($p) use ($route, &$generatedUris, $params, $pName) {
                    $this->genRoute($route, $generatedUris, array_merge($params, [$pName => $p]));
                });
                return;
            }
            $filtered = array_key_last($params) === $pName ? true : false;
        }
        if ($filtered) {
            $routeName = $route->getName();
            $generatedUris[] = [
                route($routeName, $params),
                self::getRouteFrequency($routeName),
                self::getRoutePriority($routeName)
            ];
        }
    }
}
