<?php
/**
 * Created by PhpStorm.
 * User: yilu-yj
 * Date: 2019/6/25
 * Time: 15:45
 */

namespace YiluTech\Permission\Controllers;

use YiluTech\Permission\Models\Permission;
use YiluTech\Permission\Models\PermissionTranslation;

class PermissionController
{
    public function list()
    {
    }

    public function sync()
    {
        $uris = \MicroApi::get('gateway/routes')->query(['auth_type' => 'instance_admin'])->run()->getJson();

        foreach ($uris as $item) {
            $item['type'] = 'source';
            if (empty($item['group'])) {
                $item['group'] = null;
                $items[] = array_only($item, ['name', 'type', 'extra', 'group', 'uri']);
            } else {
                foreach (explode(',', $item['group']) as $group) {
                    $item['group'] = $group == 'default' ? null : $group;
                    $items[] = array_only($item, ['name', 'type', 'extra', 'group', 'uri']);
                }
            }
        }

        return \DB::transaction(function () use ($items) {
            $origins = Permission::query()->where('type', 'source')->get();
            foreach ($items as $item) {
                $exists = false;
                foreach ($origins as $key => $origin) {
                    if ($origin->group === $item['group'] && $origin->name === $item['name']) {
                        $exists = true;
                        $origin->update($item);
                        $origins->offsetUnset($key);
                        break;
                    }
                }
                if (!$exists) {
                    Permission::create($item);
                }
            }
            if ($origins->count()) {
                Permission::query()->whereIn('id', $origins->pluck('id')->toArray())->delete();
            }
            return 'success';
        });
    }

    public function create()
    {
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)?$/', 'unique:permissions,name'],
            'type' => 'required|string|max:16',
            'group' => 'nullable|string|max:16',
            'context' => 'nullable|array'
        ])->validate();
        return Permission::query()->create($attributes);
    }

    public function update()
    {
        $permission = Permission::findById(\Request::input('id', 0));
        if (!$permission) throw new \Exception('permission not exists');
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => ['required', 'string', 'max:40', 'regex:/^[a-zA-Z0-9]+(\.[a-zA-Z0-9]+)?$/', 'unique:permissions,name' . $attributes['id']],
            'type' => 'required|string|max:16',
            'group' => 'nullable|string|max:16',
            'context' => 'nullable|array'
        ])->validate();
        return $permission->save($attributes) ? 'success' : 'fail';
    }

    public function delete()
    {
        return \DB::translation(function () {
            Permission::query()->where('name', \Request::input('name'))->delete();
            PermissionTranslation::query()->where('name', \Request::input('name'))->delete();
            return 'success';
        });
    }

    public function translate()
    {
        $attributes = \Request::input();
        \Validator::make($attributes, [
            'name' => 'required|string|max:255|exists|permission:name',
            'lang' => 'required|string|in:zh_cn,en',
            'translation' => 'required|string|max:64',
            'description' => 'nullable|string|max:255'
        ])->validate();

        $translation = PermissionTranslation::query()
            ->where('name', $attributes['name'])
            ->where('lang', $attributes['lang'])
            ->first();
        if ($translation) {
            return $translation->update($attributes) ? 'success' : 'fail';
        }
        return PermissionTranslation::query()->create($attributes);
    }

    public function removeTranslation()
    {
        return PermissionTranslation::query()->where('name', \Request::input('name'))->delete();
    }
}
