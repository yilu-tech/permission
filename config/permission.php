<?php

return [
    // 'endpoints' => [
    //     'http://examlple/admin',
    //     ['url' => 'http://examlple/admin', 'scopes' => 'staff|member'],
    //     'backend' => 'http://examlple/admin',
    // ],
    // 'local' => null, // default
    // 'server' => '',

    'user' => [
        'model' => \App\User::class
    ],

    'role' => [
        'group' => [
            'required' => false,
            /**
             * 定义group值的key, 默认从参数中取，以^开头则从header中取
             * @example "test" => "test_id"
             * @example "test" => "^test_id"
             * @example "test" => ["value" => "test_id", "scope" => "test_scope"]
             */
            'values' => [
            ],
            /**
             * 角色组所对应的选取权限范围
             * @example scope = "scope", group = "group" => permission_scope = "scope.group"
             * @example scope = "scope", group = NULL => permission_scope = "scope"
             */
            // 'scope' => ''
        ],
    ],

    'route_option' => [

    ],

    'migration_path' => 'database/permission',

    'cache' => [
        // 'connection' => 'default',
        'prefix' => 'permission',

        'expires' => 7200 // default
    ],
];
