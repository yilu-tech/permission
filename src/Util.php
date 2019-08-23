<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/26
 * Time: 9:36
 */

namespace YiluTech\Permission;


class Util
{
    public static function array_get(array &$array, string $key, callable $callback)
    {
        if (!array_key_exists($key, $array)) {
            $array[$key] = $callback();
        }
        return $array[$key];
    }

    public static function str_path_match(string $rule, string $subject, string $delimiter = '.'): bool
    {
        $units = explode($delimiter, $rule);
        $j = 0;
        $k = -1;
        foreach (explode($delimiter, $subject) as $i => $item) {
            if (empty($units[$j]) && $k === -1) return false;

            $unit = $units[$j];

            if ($unit === '**') $k = $j;

            if ($item === $unit || $unit === '*' || $unit === '**') {
                $j++;
            } else {
                if ($k === -1) return false;
                $j = $k + 1;
            }
        }
        return $j === count($units);
    }

    public static function get_query_role_group($name = 'group')
    {
        $required = config('permission.role.group.required');

        if (!\Request::has($name)) {
            if ($required) {
                throw new \Exception('role group required.');
            }
            return false;
        }
        $name = \Request::input($name);
        $value = config("permission.role.group.values.$name");

        if (!$value && !$name && !$required) return '';

        $isHeader = $value && $value{0} === '^';
        if ($isHeader) $value = substr($value, 1);

        if (!$value || ($isHeader && !\Request::header($value)) || !\Request::has($value)) {
            throw new \Exception('role group value required.');
        }
        return "$name:" . ($isHeader ? \Request::header($value) : \Request::input($value));
    }

    public static function parse_role_group($group)
    {
        $parts = explode(':', $group, 2);
        return [
            'key' => $parts[0] ?: null,
            'value' => isset($parts[1]) ? $parts[1] : null
        ];
    }
}
