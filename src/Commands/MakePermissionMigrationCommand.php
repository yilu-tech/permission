<?php


namespace YiluTech\Permission\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use YiluTech\Permission\LocalStore;
use YiluTech\Permission\PermissionException;
use YiluTech\Permission\RoutePermission;
use YiluTech\Permission\StoreManager;

class MakePermissionMigrationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:permission {--name=} {--scopes=} {--yaml} {--empty}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate permission migration file.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        try {
            if ($this->option('yaml') && !function_exists('yaml_emit_file')) {
                $this->error('yaml extension not support.');
                return;
            }

            $manager = new StoreManager(config('permission'));

            $name = $this->option('name');
            if ($name === null) {
                if (count($manager->tags()) > 1) {
                    $this->error('more than one tags, please check one.');
                    return;
                }
                $name = current($manager->tags());
            } else if (in_array($name, $manager->tags())) {
                $this->error('invalid name.');
                return;
            }

            $path = base_path(config('permission.migration_path', 'database/permission'));
            $filename = date('Y_m_d_His');
            if ($name) {
                $filename .= '_' . $name;
            }
            $type = $this->option('yaml') ? 'yaml' : 'json';
            $path = $path . DIRECTORY_SEPARATOR . $filename . '.' . $type;

            if (file_exists($path)) {
                $this->error(sprintf('file %s exists.', $path));
                return;
            }
            if ($this->option('empty')) {
                $changes = [];
            } else {
                $changes = $this->getChanges($manager->stores($name)[0]);
            }
            call_user_func([$this, 'write' . ucfirst($type)], $path, $changes);

            $this->info($path);
            $this->info('generate file success.');
        } catch (PermissionException $exception) {
            $this->error($exception->getMessage());
        }
    }

    protected function getChanges(LocalStore $store)
    {
        $origin = array_filter($store->items(), function ($item) {
            return $item['type'] === 'api';
        });

        $current = $this->getRoutePermission();
        $changes = [];
        foreach ($current as $name => $item) {
            if (empty($origin[$name])) {
                $changes[$name] = $item;
                continue;
            }
            $old = $origin[$name];
            if (!empty($diff = array_diff($old['scopes'], $item['scopes']))) {
                $changes[$name]['scopes>'] = $diff;
            }
            if (!empty($diff = array_diff($item['scopes'], $old['scopes']))) {
                $changes[$name]['scopes<'] = $diff;
            }
            if ($old['content'] != $item['content']) {
                $changes[$name]['content'] = $item['content'];
            }
            if (isset($changes[$name])) {
                $changes[$name]['action'] = 'update';
            }
            unset($origin[$name]);
        }
        foreach ($origin as $name => $item) {
            $changes[$name] = null;
        }
        return $changes;
    }

    protected function getRoutePermission()
    {
        $filter = null;
        if ($this->option('scopes')) {
            $filter = '/' . $this->option('scopes') . '/';
        }
        $items = [];
        foreach (RoutePermission::all() as $item) {
            [$name, $data] = $this->parseRoutePermission($item);

            if ($filter) {
                $scopes = $this->includeScopes($filter, $data['scopes']);
                if (empty($scopes)) {
                    continue;
                }
                $data['scopes'] = $scopes;
            }

            if (empty($items[$name])) {
                $items[$name] = $data;
            } else {
                if (Arr::isAssoc($items[$name]['content'])) {
                    $new[$name]['content'] = [$items[$name]['content'], $data['content']];
                } else {
                    $items[$name]['content'][] = $data['content'];
                }
            }
        }
        return $items;
    }

    protected function parseRoutePermission($item)
    {
        $data = [
            'type' => $item['type'],
            'scopes' => [],
            'content' => ['url' => $item['path'], 'method' => $item['method']]
        ];
        if (!empty($item['rbac_ignore'])) {
            $data['content']['rbac_ignore'] = $item['rbac_ignore'];
        }
        if ($item['auth'] === '*') {
            $data['scopes'][] = '*';
        } else {
            foreach ($item['auth'] as $auth) {
                $data['scopes'] = array_merge($data['scopes'], $this->parseRouteScope($auth));
            }
            $data['scopes'] = array_unique($data['scopes']);
            sort($data['scopes']);
        }
        return [$item['name'], $data];
    }

    protected function parseRouteScope($scope)
    {
        $parts = explode('.', $scope, 2);
        if (empty($parts[1])) {
            return [$parts[0]];
        }
        $scopes = [];
        foreach (explode(',', $parts[1]) as $item) {
            $scopes[] = $item && $item !== 'default' ? "{$parts[0]}.$item" : $parts[0];
        }
        return $scopes;
    }

    protected function includeScopes($pattern, $scopes)
    {
        $scopes = array_filter($scopes, function ($scope) use ($pattern) {
            return preg_match($pattern, $scope);
        });
        return array_values($scopes);
    }

    protected function writeJson($path, $content)
    {
        if (empty($content)) {
            $content = "{\n}";
        } else {
            $content = json_encode($content, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }
        file_put_contents($path, $content);
    }

    protected function writeYaml($path, $content)
    {
        if (empty($content)) {
            file_put_contents($path, '');
        } else {
            yaml_emit_file($path, $content, YAML_UTF8_ENCODING);
        }
    }
}
