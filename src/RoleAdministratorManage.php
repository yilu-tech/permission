<?php


namespace YiluTech\Permission;


use YiluTech\Permission\Models\Role;

class RoleAdministratorManage
{
    /**
     * @param null $group
     * @return bool
     */
    public static function exists($group = null)
    {
        return Role::query()->where('child_length', -1)->where('group', $group)->exists();
    }

    /**
     * @param null $group
     * @param string $name
     * @param array $content
     * @return Role
     * @throws \Exception
     */
    public static function add($group = null, $name = 'administrator', $content = array())
    {
        if (static::exists($group)) {
            throw new \Exception("role group[$group] administrator exists");
        }
        $content['name'] = $name;
        return Role::create($content);
    }

    /**
     * @param null $group
     * @return bool
     */
    public static function remove($group = null)
    {
        return (boolean)Role::query()->where('child_length', -1)->where('group', $group)->delete();
    }
}
