<?php

namespace Kwaadpepper\RefreshSitemap\Lib;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Route;
use Kwaadpepper\Enum\BaseEnumRoutable;
use Kwaadpepper\RefreshSitemap\Exceptions\SitemapException;
use Kwaadpepper\RefreshSitemap\Traits\SitemapRouteBinder;
use Kwaadpepper\RefreshSitemap\Traits\SitemapRouteConditions;
use Kwaadpepper\RefreshSitemap\Traits\SitemapRouteInfos;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

final class SitemapGenerator
{
    use SitemapRouteBinder;
    use SitemapRouteConditions;
    use SitemapRouteInfos;

    /** @var \Spatie\Sitemap\Sitemap */
    private $siteMap = null;

    /**
     * SitemapGenerator
     */
    public function __construct()
    {
        $this->siteMap = Sitemap::create();
    }

    /**
     * Generate the sitemap Xml
     *
     * @return string An xml file
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException If generating fails.
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
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException If generating fails.
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
     * @throws \Kwaadpepper\RefreshSitemap\Exceptions\SitemapException If generating fails.
     */
    private function generate(): void
    {
        self::initConfig();
        $this->assertRouteBinderIsCorrect();

        $routeCollection = Route::getRoutes();

        /**
         * @var array<array<int,string|float>>
         */
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
            $this->siteMap->add(Url::create(\strval($smRules[0]))
                ->setLastModificationDate(Carbon::yesterday())
                ->setChangeFrequency(\strval($smRules[1]))
                ->setPriority(\floatval($smRules[2])));
        }
    }

    /**
     * Init variables from configuration
     *
     * @return void
     */
    private static function initConfig(): void
    {
        self::initConfigBinder();
        self::initConfigConditions();
        self::initConfigInfo();
    }

    /**
     * Generate route uri for site map usage
     *
     * @param \Illuminate\Routing\Route         $route
     * @param array<array[string,string,float]> $generatedUris
     * @return void
     */
    private function handleRoute(\Illuminate\Routing\Route $route, array &$generatedUris): void
    {
        /** @var array to be populated with models from DB to generate a route */
        $rParams = [];

        /** @var array<\ReflectionParameter> parameters signature from the controller */
        $params = $route->signatureParameters();
        /** @var array<string> parameters name from the registered route */
        $pNames = $route->parameterNames();

        $hasModels = false;

        $rParams = $this->handleParams($params, $pNames, $route, $hasModels);

        // If the route has no dynamic models as parameters.
        if (!count($params) or !$hasModels) {
            $rName           = $route->getName();
            $generatedUris[] = [
                route($route->getName()),
                self::getRouteFrequency($rName),
                self::getRoutePriority($rName)
            ];
        } else {
            // If the route has dynamic models as parameters.
            $this->genRoute($route, $generatedUris, $rParams);
        }
    }

    /**
     * Handle all route params
     *
     * @param array<\ReflectionParameter> $params
     * @param array<string>|null          $pNames
     * @param \Illuminate\Routing\Route   $route
     * @param boolean                     $hasModels
     * phpcs:ignore Generic.Files.LineLength.TooLong
     * @return array<string,array<string,\Illuminate\Database\Eloquent\Model,\Kwaadpepper\Enum\BaseEnumRoutable>> List of parameters resolved.
     * @throws SitemapException If a class on a route is not handled.
     */
    private function handleParams(
        array $params,
        array $pNames = null,
        \Illuminate\Routing\Route $route,
        bool &$hasModels
    ): array {
        /** @var array Resolved parameters. */
        $rParams = [];
        foreach ($params as $param) {
            $name = null;
            if ($paramType = $param->getType() and !$paramType->isBuiltin()) {
                $name = new \ReflectionClass($paramType->getName());
            }

            /** @var string $pName $parameter name on route uri */
            $pName = '';

            // If the route has parameters and the controller accepts it.
            if (count($pNames) and \array_key_exists($param->getPosition(), $pNames)) {
                // Get the controller parameter name for the route.
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

            // If We have a custom binder for this route.
            if ($this->hasRouteBinderParam($route, $pName)) {
                $hasModels = true;
                $rParams  += $this->processWithRouteBinderParam($route, $pName);
                continue;
            }

            // If This route takes a model as param.
            if ($name and $name->isSubclassOf(Model::class)) {
                $hasModels = true;
                /** @var \string */
                $modelClassName = $name->getName();
                $rParams       += $this->setParamsForModel($modelClassName, $pName);
                continue;
            }
        } //end foreach
        return $rParams;
    }

    /**
     * Process route with binderParam
     *
     * @param \Illuminate\Routing\Route $route
     * @param string                    $pName
     * phpcs:ignore Generic.Files.LineLength.TooLong
     * @return array<string,array<integer,string|\Illuminate\Database\Eloquent\Model|\Kwaadpepper\Enum\BaseEnumRoutable>> List of parameters resolved.
     * @throws SitemapException If a class on a route is not handled.
     */
    private function processWithRouteBinderParam(\Illuminate\Routing\Route $route, string $pName): array
    {
        /** @var array Resolved parameters. */
        $rParams = [];

        $o = $this->getRouteBinderParam($route, $pName);

        $modelClassName = $o[0];
        $routeParamName = $o[1];
        $rfLCls         = (new \ReflectionClass($modelClassName));

        switch (true) {
            case $rfLCls->isSubclassOf(Model::class):
                $rParams = $this->setParamsForModel($modelClassName, $pName, $routeParamName);
                break;
            case $rfLCls->isSubclassOf(BaseEnumRoutable::class):
                $rParams = $this->setParamForEnum($rfLCls->getName(), $pName);
                break;
            default:
                throw new SitemapException(trans('Unhandled class type :className', [
                    'className' => $rfLCls->getName()
                ]));
        }
        return $rParams;
    }

    /**
     * Set route param Array for Enums
     *
     * @param string $enumClassName
     * @param string $pName
     * @return array<string,array<integer,\Kwaadpepper\Enum\BaseEnumRoutable>> List of parameters resolved.
     */
    private function setParamForEnum(string $enumClassName, string $pName): array
    {
        /** @var array Resolved parameters. */
        $rParams = [];
        foreach (forward_static_call(sprintf('%s::toArray', $enumClassName)) as $enum) {
            if (!array_key_exists($pName, $rParams)) {
                $rParams[$pName] = [];
            }
            $rParams[$pName][] = $enum->value;
        }
        return $rParams;
    }

    /**
     * Set route param Array for Models
     *
     * @param string $modelClassName
     * @param string $pName
     * @param string $routeParamName
     * phpcs:ignore Generic.Files.LineLength.TooLong
     * @return array<string,array<integer,string|\Illuminate\Database\Eloquent\Model>> List of parameters resolved.
     */
    private function setParamsForModel(
        string $modelClassName,
        string $pName,
        string $routeParamName = null
    ): array {
        /** @var array Resolved parameters. */
        $rParams = [];
        $i       = 0;
        $limit   = 10;
        /** @var string $modelTable */
        $modelTable = (new \ReflectionClass($modelClassName))->newInstanceWithoutConstructor()->getTable();
        $q          = $modelClassName::query();
        self::queryConditions($q, $modelTable);
        do {
            $results = $q->offset($i * $limit)->limit($limit)->get();
            $length  = count($results);
            $results->map(function (Model $model) use (&$rParams, $pName, $routeParamName) {
                if (!array_key_exists($pName, $rParams)) {
                    $rParams[$pName] = [];
                }
                $rParams[$pName][] = $routeParamName ? $model[$routeParamName] : $model;
            });
            $i++;
        } while ($length);
        return $rParams;
    }

    /**
     * Recursively generate routes for all params
     *
     * @param \Illuminate\Routing\Route      $route
     * @param array<array<int,string|float>> $generatedUris
     * @param array                          $params
     * @return void
     */
    private function genRoute(\Illuminate\Routing\Route $route, array &$generatedUris, array $params)
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
            $routeName       = $route->getName();
            $generatedUris[] = [
                route($routeName, $params),
                self::getRouteFrequency($routeName),
                self::getRoutePriority($routeName)
            ];
        }
    }
}
