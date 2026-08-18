<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Filesystem Disk
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default filesystem disk that should be used
    | by the framework. The "local" disk, as well as a variety of cloud
    | based disks are available to your application for file storage.
    |
    */

    'default' => env('FILESYSTEM_DISK', 'local'),

    /*
    |--------------------------------------------------------------------------
    | Filesystem Disks
    |--------------------------------------------------------------------------
    |
    | Below you may configure as many filesystem disks as necessary, and you
    | may even configure multiple disks for the same driver. Examples for
    | most supported storage drivers are configured here for reference.
    |
    | Supported drivers: "local", "ftp", "sftp", "s3"
    |
    */

    'disks' => [

        'local' => [
            'driver' => 'local',
            'root' => storage_path('app/private'),
            'serve' => true,
            'throw' => false,
            'report' => false,
        ],

        'public' => [
            'driver' => 'local',
            'root' => public_path('storage'),
            'url' => env('APP_URL').'/storage',
            'visibility' => 'public',
            'throw' => false,
            'report' => false,
        ],

        's3' => [
            'driver' => 's3',
            'key' => env('AWS_ACCESS_KEY_ID'),
            'secret' => env('AWS_SECRET_ACCESS_KEY'),
            'region' => env('AWS_DEFAULT_REGION'),
            'bucket' => env('AWS_BUCKET'),
            'url' => env('AWS_URL'),
            'endpoint' => env('AWS_ENDPOINT'),
            'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
            'throw' => false,
            'report' => false,
        ],

        'r2' => [
            'driver' => 's3',
            'key' => '5721b7618726eb9f08964edde9406957',
            'secret' => '664667c283dcba8e448194132d4d615a982bddff6cf5405eaa469edf00d580e6',
            'region' => 'auto',
            'bucket' => 'epal-chat',
            'endpoint' => 'https://0638d2ae9512092bcdd276d1c06e7ada.r2.cloudflarestorage.com',
            'public_url' => 'https://pub-176fe1d6d2344f978382780342e73103.r2.dev',
            'use_path_style_endpoint' => true,
            'visibility' => 'public',
        ],
        // 'r2' => [
        //     'driver' => 's3',
        //     'key' => env('R2_ACCESS_KEY_ID'),
        //     'secret' => env('R2_SECRET_ACCESS_KEY'),
        //     'region' => env('R2_REGION', 'auto'),
        //     'bucket' => env('R2_BUCKET'),
        //     'endpoint' => env('R2_ENDPOINT'),
        //     'use_path_style_endpoint' => true,
        //     'visibility' => 'public',
        // ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Symbolic Links
    |--------------------------------------------------------------------------
    |
    | Here you may configure the symbolic links that will be created when the
    | `storage:link` Artisan command is executed. The array keys should be
    | the locations of the links and the values should be their targets.
    |
    */

    'links' => [
        public_path('storage') => storage_path('app/public'),
    ],

];
