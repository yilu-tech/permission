<?php


namespace YiluTech\Permission;


use Illuminate\Support\Arr;

class Utils
{
    public static function data_merge(&$target, $data, $right = true)
    {
        if (is_string($target)) {
            if ($right) {
                $target .= (string)$data;
            } else {
                $target = (string)$data . $target;
            }
        } else if (is_array($target)) {
            if ($right) {
                $target = array_merge($target, (array)$data);
            } else {
                $target = array_merge((array)$data, $target);
            }
        } else if (is_float($target)) {
            $target += (float)$data;
        } else if (is_int($target)) {
            $target += (int)$data;
        } else {
            $target = $data;
        }
    }

    public static function data_split(&$target, $data, $right = true)
    {
        if (is_null($data)) {
            $target = $data;
        } else if (is_string($target)) {
            $data = (string)$data;
            if ($right) {
                if (substr_compare($target, $data, $offset = -strlen($data)) === 0) {
                    $target = substr($target, 0, $offset);
                }
            } else {
                if (strncmp($target, $data, $offset = strlen($data)) === 0) {
                    $target = substr($target, $offset);
                }
            }
        } else if (is_array($target)) {
            $data = (array)$data;
            $argv = $right ? [$target, $data] : [$data, $target];
            if (Arr::isAssoc($target)) {
                $target = array_diff_assoc(...$argv);
            } else {
                $target = array_values(array_diff(...$argv));
            }
        } else if (is_float($target)) {
            if ($right) {
                $target -= (float)$data;
            } else {
                $target = (float)$data - $target;
            }
        } else if (is_int($target)) {
            if ($right) {
                $target -= (int)$data;
            } else {
                $target = (int)$data - $target;
            }
        }
    }

    public static function data_del(&$target, $key)
    {
        $segments = is_array($key) ? $key : explode('.', $key);
        $key = array_pop($segments);

        $exists = true;
        while (count($segments)) {
            $segment = array_shift($segments);
            if (isset($target[$segment])) {
                $target = &$target[$segment];
            } else {
                $exists = false;
                break;
            }
        }
        if ($exists) {
            unset($target[$key]);
        }
    }
}
