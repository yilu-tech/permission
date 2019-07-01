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

}
