<?php

return [
    'identity' => [
        'names' => [],

        'unique' => true,

        'system' => true,

        'default' => true
    ],

    'user' => [
        'model' => \App\User::class
    ],

    'migration_path' => '',

    'cache_prefix' => '',
];
