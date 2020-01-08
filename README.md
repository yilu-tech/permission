#### 配着说明
权限采用中心化存储、管理，其它服务分别向中心服务提供权限。

`remote`: 设置中心服务地址，未设置则表示为中心服务；

`server`: 服务名称，每个服务名称唯一；

`migration_path`: 权限存储路径，默认为:database/permission/permission.log

#### 路由权限
路由的`name`即为权限的名称, 路由新增 `name_prefix` name前缀配置，连接符为 `.`；  
定义规则： 授权组|...@!名称  
一个权限可以授权多个组；  
`!`表示权限不经过rbac认证  

#### 命令说明
`permission:record`: 将当前服务的权限变动写入本地文件  
            --auth=: 过滤授权组  
            --db: 将本地变动信息写入数据库  
            --not-ignore：记录忽略的权限  
常用说明：在提交代码之前，先执行`permission:record`命令，权限变动会随着git记录提交到代码库，在服务器更新代码之后再执行`permission:record --db`将记录写入数据库。

`permission:rollback {date}`: 回滚权限, date：回滚的日期，若为null则清除当前服务的所有权限；

#### 初始角色
通过DB seeder 实现。
角色说明：
- name: 角色名称，在同一用户组下唯一
- alias：角色别名，全局唯一
- group：用户组，可为空
- status：角色状态
  - RS_ADMIN：管理员
  - RS_BASIC：基础角色，当用户加入用户组时会默认给予当前角色
  - RS_SYS：系统角色，不能删除
  - RS_READ：可显示
  - RS_WRITE：可修改
  - RS_EXTEND：可继承

例：
```php
    $roles = [
         ['name' => '基础功能', 'alias' => 'city_basic', 'group' => 'city', 'status' => RS_BASIC | RS_SYS],
         ['name' => '管理员', 'alias' => 'hall_admin', 'group' => 'hall', 'status' => RS_ADMIN | RS_SYS | RS_READ],
         ['name' => '管理员', 'alias' => 'admin', 'status' => RS_ADMIN | RS_SYS | RS_READ],
         ['name' => '教练', 'alias' => 'coach', 'status' => RS_SYS | RS_READ | RS_WRITE],
         ['name' => '销售员', 'alias' => 'salesman', 'group' => 'hall', 'status' => RS_SYS | RS_READ | RS_WRITE]
     ];

     foreach ($roles as $role) {
         \YiluTech\Permission\Models\Role::create($role);
     }
```
