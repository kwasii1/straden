<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Built-in InfluxDB connection
    |--------------------------------------------------------------------------
    |
    | Connection details for the built-in InfluxDB instance that stores k6
    | load test metrics. Used when seeding the system connector. Read via
    | config() so the values survive configuration caching.
    |
    */

    'host' => env('INFLUXDB_HOST', '127.0.0.1'),
    'port' => env('INFLUXDB_PORT', 8086),
    'database' => env('INFLUXDB_DB', 'k6'),
];
