<?php


namespace YiluTech\Permission\Helper;


use YiluTech\Permission\PermissionException;

class RoleGroup
{
    public static function config($key = null, $default = null)
    {
        static $config;
        if ($config === null) {
            $config = config("permission.role.group", []);
        }
        return data_get($config, $key, $default);
    }

    public static function getFromQuery($name = 'group')
    {
        if (!\Request::has($name)) {
            if (static::config('required', false)) {
                throw new PermissionException('Role group :name required.', ['name' => $name]);
            }
            return false;
        }
        return static::make(\Request::input($name));
    }

    public static function make($group)
    {
        if (empty($group)) {
            $group = null;
        } else if (!is_null($value = static::value($group))) {
            $group .= ':' . $value;
        }
        return $group;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $group
     * @param string $key
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function bindQuery($query, $group, $key = 'group')
    {
        $info = RoleGroup::parse($group);
        if (empty($info['key'])) {
            return $query->whereNull($key);
        }
        if (empty($info['value'])) {
            return $query->where($key, $info['key']);
        }
        return $query->where(function ($query) use ($info, $group, $key) {
            $query->where($key, $info['key'])->orWhere($key, $group);
        });
    }

    public static function parse($group, $name = null)
    {
        $config = static::config();
        $info['scope'] = $config['scope'] ?? null;

        if ($group === null) {
            $info['key'] = null;
            $info['value'] = null;
        } else {
            $segments = explode(':', $group, 2);
            if (isset($config['values'][$segments[0]])) {
                $info['key'] = $segments[0];
                $info['value'] = $segments[1] ?? null;
                $info['scope'] = $config['values'][$segments[0]]['scope'] ?? $info['scope'];
            } else {
                $info['key'] = $group;
                $info['value'] = null;
            }
        }
        return $name ? $info[$name] : $info;
    }

    public static function value($group)
    {
        $value = static::config(['values', $group]);
        if (is_array($value)) {
            $value = $value['value'];
        }

        if (empty($value)) {
            return null;
        }

        if ($isHeader = $value[0] === '^') {
            $value = substr($value, 1);
        }

        $data = $isHeader ? \Request::header($value) : \Request::input($value);

        if (is_null($data)) {
            throw new PermissionException("Role group :name value :value required.", ['name' => $group, 'value' => $value]);
        }
        return $data;
    }
}
