<?php

use Spatie\Sitemap\Tags\Url;

return [

    /**
     * A list of url to ignore
     * These are hard coded
     * Each url does not have to be exhaustive
     *
     * For example, if you want to ignore all routes
     * of the backoffice group
     * just add '/backoffice' into this table
     *
     * * Note that this can take route names or route urls (both partially)
     */
    'ignoreRoutes' => [],

    /**
     * This table is needed to resolve models matching
     * routes
     *
     * it has to be filled like following
     *
     *   'products.meat-type' => [
     *       'slugMeatType' => [MeatTypes::class, 'slug']
     *   ],
     *   'kitchen-side.health-nutrition.balanced-menu.menu' => [
     *       'type' => [BalancedMenuType::class, 'type']
     *   ]
     *
     * The index is the complete route name, the array content is an array
     * within index is the argument name with its model class.
     * This will allow the generator to generate a route for each of the models
     * in this table
     */
    'routesBinder' => [],

    /**
     * You can put route conditions here
     * These will be used to decide whether the route should appear
     * in the sitemap or not
     *
     * These are called in Eloquent where
     *
     * example
     *
     *   'queryConditions' => [
     *       'status' => true,
     *       'ocbStatus' => EleveurOcbStatus::yes()->value,
     *       'star_moment' => true
     *   ]
     *
     */
    'queryConditions' => [],

    /**
     * The default route frequency to use with sitemap generator
     */
    'routeDefaultFrequency' => Url::CHANGE_FREQUENCY_DAILY,

    /**
     * The default route priority to use with sitemap generator
     *
     * priority should be a number between 0 and 1
     * https://www.sitemaps.org/protocol.html
     */
    'routeDefaultPriority' => 0.5,

    /**
     * Frequencies for routes
     * use the full or partial route name as index
     * and a frequency within \Spatie\Sitemap\Tags\Url constants
     */
    'routeFrequencies' => [],

    /**
     * Priorities for routes
     * use the full or partial route name as index
     * and a priority number between 0 and 1
     */
    'routePriorities' => [],

    /**
     * Enable or disable schedule for the job
     * If set to true the sitemap generator will be run
     * using the 'cron' instruction
     */
    'schedule' => false,

    /**
     * If you want to set specific
     * instruction for when to schedule job
     * you can set this with a cron instruction
     * You can generate one using https://crontab.guru/
     * https://laravel.com/docs/8.x/scheduling#schedule-frequency-options
     *
     * schedule must be set to true
     *
     * Default is every day at 15:45
     */
    'cron' => '45 15 * * *'
];
