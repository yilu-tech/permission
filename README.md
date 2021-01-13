# 权限包说明

## artisan 命令
* vendor:publish --tag=permission-migrations --force : 生成数据库表文件

* vendor:publish --tag=permission-config --force : 生成配置文件
    
* make:permission : 用于生成权限文件, 默认文件内容为`json`格式
    * --name : 当`endpoints`有多个时, 用于指定`endopint`名
    * --scopes : 用于指定权限组, 默认对`endpoints`中定义的`scopes`进行过滤筛选
    * --yml : 生成文件内容为`yaml`格式
    * --empty : 生成空文件, 默认写入差异路由权限
    * --db : 从数据据库比对差异,默认从文件比对差异

* permission:migrate : 写入权限操
    * --test : 测试migration文件生成差异信息, 逆向读取 N 个测试文件 [default: "N"]
    * --db : 从数据库比对进行测试

* permission:rollback : 回滚权限操作
    * --steps : 回滚次数 [default: "1]

* permission:merge : 将本地多个权限文件合并成单个文件 (当数据库表`permission_migrations`为空时, 可从`permissions`表中读取当前权限,并生成当前数据库中对应权限文件)
    * --yaml : 生成yaml格式文件,需要php yaml扩展支持
        
## 配置文件

* server : 当前服务名
* endopoints : 权限提交节点

    例1: member-service 作为用户端, staff-service 作为服务端
    ```php
    /**
        * member-service
        * config/permission.php
        */

    <?php

    return [
        'server' => 'member'

        'endpoints' => [
            'staff' => [
                'url' => 'http://api.test.com/staff',
                'scopes' => 'admin',
            ]
        ];
    ];

    // 以上配置亦可简写成如下形式
    return [
        'server' => 'member'

        'endpoints' => 'http://api.test.com/staff',
    ];
    ```

    ```php
    /**
        * staff-service
        * config/permission.php
        */

    <?php

    return [
        'server' => 'staff'

        'local' => [
            'name' => 'admin', 'scopes' => 'admin'
        ],
    ];
    ```

    例2: member-service 作为用户端, staff-service 同时作为用户端和服务端, management 用作服务端
    ```php
    /**
        * member-service
        * config/permission.php
        */

    <?php

    return [
        'server' => 'member'

        'endpoints' => [
            'staff' => [
                'url' => 'http://api.test.com/staff',
                'scopes' => 'admin',
            ],
            'management' => [
                'url' => 'http://api.test.com/management',
                'scopes' => 'management',
            ],
        ];
    ];
    ```

    ```php
    /**
        * staff-service
        * config/permission.php
        */

    <?php

    return [
        'server' => 'staff'

        'local' => [
            'name' => 'admin', 'scopes' => 'admin'
        ],

        'endpoints' => [
            'management' => [
                'url' => 'http://api.test.com/management',
                'scopes' => 'management',
            ],
        ]
    ];
    ```

## 权限文件

1. json 格式说明

    ```json
    {
        "@user.create": {         // @ 表示修改
            "type": "api",
            "scopes<": [            // 表示向scopes追加
                "admin",
            ],
            "scopes>": [            // 表示从scopes移除
                "admin.xxx",
            ],
            "content": {
                "url": "\/user\/create",
                "method": "POST"
            },
            "translations.cn": {
                "content": "翻译",
                "description": "描述",
            }

        },
        "user.list": {          // 表示添加
            "type": "api",
            "scopes": [],
            "content": {
                "url": "\/user\/list",
                "method": "GET"
            },
            "translations": {
                "cn": {
                    "content": "翻译",
                    "description": "描述"
                }
            }
        },
        "user.detail": null     // 表示删除
    }
    ```

2. yaml 格式说明

    ```yaml
    '@user.create':
      type: api
      scopes:
        - admin
      content:
        url: user/create
        method: POST
      translations.cn:
        content: 翻译
        description: 描述
    user.list:
      type: api
      scopes:
      content:
        url: user/list
        method: GET
      translations:
        cn:
          content: 翻译
          description: 描述
    user.detail:
    ```
