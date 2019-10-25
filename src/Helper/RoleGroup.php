<?php


namespace YiluTech\Permission\Helper;


class RoleGroup
{
    public static function config($name = null, $default = null)
    {
        $config = config("permission.role.group", []);
        return $name === null ? $config : ($config[$name] ?? $default);
    }

    public static function getFromQuery($name = 'group')
    {
        if (!\Request::has($name)) {
            if (static::config('required', false)) {
                throw new \Exception("Role group<$name> required.");
            }
            return false;
        }
        $group = \Request::input($name);
        return $group ? "$group:" . static::value($group) : null;
    }

    /**
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string $group
     * @param string $key
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public static function bindQuery($query, $group, $key = 'group')
    {
        if ($group === null) {
            $query->whereNull($key);
        } elseif ($group) {
            $query->where(function ($query) use ($group, $key) {
                $query->where($key, $group);
                if ($group = strstr($group, ':', true)) {
                    $query->orWhere($key, $group);
                } else {
                    $query->orWhereNull($key, $group);
                }
            });
        }
        return $query;
    }

    public static function scope($group)
    {
        $config = static::config();
        return $config['values'][$group]['scope'] ?? $config['scope'] ?? null;
    }

    public static function parse($group, $name = null)
    {
        $parts = explode(':', $group, 2);
        $info['value'] = $parts[1] ?? null;

        $parts = array_map('strrev', explode('.', strrev($parts[0]), 2));
        $info['scope'] = $parts[1] ?? null;

        $info['key'] = $parts[0];
        return $name ? $info[$name] : $info;
    }

    public static function value($group)
    {
        $config = static::config();

        $required = $config['required'] ?? false;

        $value = $config['values'][$group] ?? '';

        if (is_array($value)) {
            $value = $value['value'];
        }

        if (!$value && !$group && !$required) return null;

        $isHeader = $value && $value{0} === '^';

        if ($isHeader) {
            $value = substr($value, 1);
        }

        if (!$value || ($isHeader && !\Request::hasHeader($value)) || !\Request::has($value)) {
            throw new \Exception("Role group<$group> value<$value> required.");
        }

        return $isHeader ? \Request::header($value) : \Request::input($value);
    }
}
