<?php

return [

    /*
     * The route prefix for Mixpost web interface.
     */
    'route_prefix' => env('MIXPOST_ROUTE_PREFIX', 'mixpost'),

    /*
     * The route prefix for Mixpost core API.
     */
    'api_route_prefix' => env('MIXPOST_API_ROUTE_PREFIX', 'mixpost/api'),

    /*
     * The disk on which to store uploaded media files.
     */
    'disk' => env('MIXPOST_DISK', env('FILESYSTEM_DISK', 's3')),

    /*
     * Cache prefix.
     */
    'cache_prefix' => 'mixpost',

    /*
     * Define default user authentication guard or middleware.
     */
    'middleware' => ['web'],

    /*
     * The log channel that should be used to log Mixpost events.
     */
    'log_channel' => env('LOG_CHANNEL', 'stderr'),

    /*
     * Define whether Mixpost runs user registration or is single-admin.
     */
    'single_user' => env('MIXPOST_SINGLE_USER', true),

];
