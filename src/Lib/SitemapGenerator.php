<?php

namespace Kwaadpepper\RefreshSitemap\Lib;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
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
     * Default routes to ignore
     *
     * @var string[]
     */
    private static $defaultIgnoreRoutes = [
        'ignition.'
    ];

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

        /** @var \Illuminate\Routing\Router $router */
        $router = app('router');

        $routeCollection = $router->getRoutes();

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
        /** @var \Illuminate\Support\Collection<\ReflectionParameter> parameters signature from the controller */
        $params = collect($route->signatureParameters());
        /** @var \Illuminate\Support\Collection<string> parameters name from the registered route */
        $pNames = collect($route->parameterNames());

        // * Filter controller params and keep only : either if is a LaravelModel or is declared as route parameter.
        $params = \collect($params)->filter(function (\ReflectionParameter $param) use ($pNames) {
            $extendsLaravelModel = false;
            if ($paramType = $param->getType() and !$paramType->isBuiltin()) {
                $extendsLaravelModel = (new \ReflectionClass($paramType->getName()))->isSubclassOf(Model::class);
            }
            return $extendsLaravelModel or $pNames->contains($param->getName());
        });

        $this->handleParams($params->all(), $pNames->all(), $route, $generatedUris);
    }

    /**
     * Handle all route params
     *
     * @param array<\ReflectionParameter>       $params
     * @param array<string>|null                $pNames
     * @param \Illuminate\Routing\Route         $route
     * @param array<array[string,string,float]> $generatedUris
     * @return void
     * @throws SitemapException If a class on a route is not handled.
     */
    private function handleParams(
        array $params,
        array $pNames = null,
        \Illuminate\Routing\Route $route,
        array &$generatedUris
    ): void {
        $hasModels   = false;
        $hasNullable = false;
        /** @var array<string,array<string,\Illuminate\Database\Eloquent\Model,\Kwaadpepper\Enum\BaseEnumRoutable>> List of parameters resolved. */
        $rParams = [];
        /** @var array<string,array<string,\Illuminate\Database\Eloquent\Model,\Kwaadpepper\Enum\BaseEnumRoutable>> List of parameters resolved. */
        $rParamsNullable = [];
        foreach ($params as $param) {
            $name        = null;
            $hasNullable = $hasNullable || $param->allowsNull();
            if ($paramType = $param->getType() and !$paramType->isBuiltin()) {
                $name = new \ReflectionClass($paramType->getName());
            }

            /** @var string $pName $parameter name on route uri */
            $pName = $param->getName();

            // * If the route has parameters and the controller accepts it.
            if (count($pNames) and \array_key_exists($param->getPosition(), $pNames)) {
                // Get the controller parameter name for the route.
                $pName = $pNames[$param->getPosition()];
            }

            $this->debugHandlingParams(
                $route->getName(),
                $name ? $name->getName() : '',
                $pName,
                $this->hasRouteBinderParam($route, $pName),
                $param->allowsNull()
            );

            // * If We have a custom binder for this route.
            if ($this->hasRouteBinderParam($route, $pName)) {
                $hasModels        = true;
                $rParams         += $this->processWithRouteBinderParam($route, $pName);
                $rParamsNullable += $param->allowsNull() ?
                    [$pName => null] : $this->processWithRouteBinderParam($route, $pName);
                continue;
            }

            // * If This route takes a model as param.
            if ($name and $name->isSubclassOf(Model::class)) {
                $hasModels        = true;
                $modelClassName   = $name->getName();
                $rParams         += $this->setParamsForModel($modelClassName, $pName);
                $rParamsNullable += $param->allowsNull() ?
                    [$pName => null] : $this->setParamsForModel($modelClassName, $pName);
                continue;
            }
        } //end foreach


        // * If the route has no dynamic models as parameters.
        if (!count($params) or !$hasModels) {
            $rName           = $route->getName();
            $generatedUris[] = [
                route($route->getName()),
                self::getRouteFrequency($rName),
                self::getRoutePriority($rName)
            ];
            return;
        }
        // * If the route has dynamic models as parameters.
        $this->genRoute($route, $generatedUris, $rParams);
        // * If then route has nullable parameters.
        if ($hasNullable) {
            $this->genRoute($route, $generatedUris, $rParamsNullable);
        }
    }

    /**
     * Print console debug
     *
     * @param string  $routeName
     * @param string  $typeName
     * @param string  $paramName
     * @param boolean $hasBinder
     * @param boolean $nullable
     * @return void
     */
    private function debugHandlingParams(
        string $routeName,
        string $typeName,
        string $paramName,
        bool $hasBinder,
        bool $nullable
    ): void {
        if (config('app.debug')) {
            dump(\trans(
                // phpcs:ignore Generic.Files.LineLength.TooLong
                'Route name : :routeName, Param [type: :typeName, name: :paramName, binder: :hasBinder, nullable: :nullable]',
                [
                    'routeName' => $routeName,
                    'typeName' => $typeName,
                    'paramName' => $paramName,
                    'hasBinder' => $hasBinder ? 'Yes' : 'No',
                    'nullable' => $nullable ? 'Yes' : 'No'
                ]
            ));
        }
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
        /** @var object */
        $instance = (new \ReflectionClass($modelClassName))->newInstanceWithoutConstructor();
        /** @var string $modelTable */
        $modelTable = $instance->getTable();
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

        // * Route name matches params
        if ($filtered and $this->routeBindingsCanBeResolved($route, $params) and ($routeName = $route->getName())) {
            $generatedUris[] = [
                route($routeName, $params),
                self::getRouteFrequency($routeName),
                self::getRoutePriority($routeName)
            ];
        } //end if
    }

    /**
     * Can route params be resolved ?
     *
     * @param \Illuminate\Routing\Route $route
     * @param array                     $params
     * @return boolean
     */
    private function routeBindingsCanBeResolved(\Illuminate\Routing\Route $route, array $params): bool
    {
        if (!($routeName = $route->getName())) {
            return false;
        }
        $request = Request::create(route($routeName, $params));

        $route = $route->bind($request);

        /** @var \Illuminate\Routing\Router $router */
        $router = app('router');

        try {
            $router->substituteBindings($route);
            $router->substituteImplicitBindings($route);
        } catch (ModelNotFoundException $e) {
            return false;
        }
        return true;
    }
}
