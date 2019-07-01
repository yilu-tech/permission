<?php

namespace YiluTech\Permission;


class Identity
{
    public static function getScopeKeys()
    {
        $keys = array();
        foreach (config('permission.identity.names', []) as $index => $value) {
            $keys[] = "scope_$index";
        }
        return $keys;
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     * @return array
     */
    public static function getScopeValues($model)
    {
        return array_intersect_key($model->getOriginal(), array_flip(Identity::getScopeKeys()));
    }

    /**
     * @param $model \Illuminate\Database\Eloquent\Model
     * @return string
     */
    public static function getCacheKey($model)
    {
        $scopeValue = array_values(static::getScopeValues($model));

        if (!config('permission.identity.unique')) {
            return implode($scopeValue, '.');
        }

        if ($index = array_search(0, $scopeValue, true)) {
            $index = $index - 1;
        } else {
            $index = count($scopeValue) - 1;
        }

        if ($index < 0) {
            return '';
        }

        return config("permission.identity.names.$index") . ':' . $scopeValue[$index];
    }

    public static function whereIdentity($query, $identity, $system = null, $table = 'user_has_roles')
    {
        $identity = self::formatIdentity($identity);

        $lastIndex = count($identity) - 1;

        $unique = config('permission.identity.unique');
        $default = config('permission.identity.default');

        if ($system === null) {
            $system = config('permission.identity.system');
        }

        foreach ($identity as $key => $value) {
            $field = "$table.scope_$key";

            if (!$unique || $value === 0 || $key === $lastIndex) {
                $query->where($field, $value);
                if ($value === 0) break;
            }

            $nextIndex = $key + 1;
            if ($nextIndex < $lastIndex && $identity[$nextIndex] === 0) {
                if ($value === -1) {
                    $query->where($field, $value);
                    continue;
                }
                $query->where(function ($query) use ($field, $value, $system, $default) {
                    $query->where($field, $value);
                    if ($system) $query->orWhere($field, -1);
                    if ($default) $query->orWhere($field, 0);
                });
            }
        }
        return $query;
    }

    public static function formatIdentity($identity)
    {
        $format = [];

        $is_split = false;

        $keys = config('permission.identity.names', []);

        foreach (static::getScopeKeys() as $index => $value) {
            if ($is_split) {
                $format[] = 0;
                continue;
            }

            if (isset($identity[$keys[$index]])) {
                $value = $identity[$keys[$index]];
            } elseif (!isset($identity[$index])) {
                $value = 0;
            }

            $format[] = (int)$value;

            if (!$is_split && $value == 0) {
                $is_split = true;
            }
        }

        return $format;
    }
}
