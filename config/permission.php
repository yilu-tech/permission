<?php

return [
    'remote' => '',

    'user' => [
        'model' => \App\User::class
    ],

    'role' => [
        'group' => [
            'required' => false,
            /**
             * 定义group值的key, 默认从参数中取，以^开头则从header中取
             */
            'values' => [

            ]
        ],
    ],

    'route_option' => [

    ],

    'migration_path' => 'database/permission',

    'cache' => [
        'prefix' => 'permission',
        'expire' => 15
    ],
];
