<?php

return [
    // 'remote' => [
    //     '@admin' => 'http://examlple/admin'
    // ],

    // 'local' => '*',

    // 'local' => '*',

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
        'prefix' => 'permission',
        'expire' => 15 // day
    ],
];
