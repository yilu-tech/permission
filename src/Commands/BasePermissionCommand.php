<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use League\Flysystem\Util;
use YiluTech\Permission\RoutePermission;

class BasePermissionCommand extends Command
{
    /**
     * @var Filesystem
     */
    protected $file;

    public function __construct(Filesystem $filesystem)
    {
        parent::__construct();

        $this->file = $filesystem;
    }

    protected function getRoutePermission()
    {
        $items = [];
        foreach (RoutePermission::all() as $item) {
            $items[$item['name']] = $item;
        }
        return $items;
    }

    protected function getChanges($new, $old)
    {
        $differ = function ($a, $b) {
            return $a == $b ? 0 : 1;
        };
        $changes = [];
        foreach (array_udiff_assoc($new, $old, $differ) as $key => $item) {
            if (isset($old[$key])) {
                $item['action'] = 'update';
                $item['changes'] = array_udiff_assoc($old[$key], $item, $differ);
            } else {
                $item['action'] = 'create';
            }
            $changes[$key] = $item;
        }
        foreach (array_udiff_assoc($old, $new, $differ) as $key => $item) {
            if (empty($new[$key])) {
                $item['action'] = 'delete';
                $changes[$key] = $item;
            }
        }
        return $changes;
    }

    protected function getLastStored()
    {
        $items = $this->getStored();
        foreach ($this->getChanged() as $key => $value) {
            if ($value['action'] === 'create') {
                unset($items[$key]);
            } else {
                if ($value['action'] === 'update') {
                    $value = array_merge($value, $value['changes']);
                    unset($value['changes']);
                }
                unset($value['action']);

                $items[$key] = $value;
            }
        }
        return $items;
    }

    protected function getStored()
    {
        $path = $this->getPath("permissions.php");
        if ($this->file->isFile($path)) {
            return require $path;
        }
        return [];
    }

    protected function getChanged()
    {
        $path = $this->getPath('changes.php');
        if ($this->file->isFile($path)) {
            return require $path;
        }
        return [];
    }

    protected function getPath(string $name = '')
    {
        $path = $this->option('path') ?: config('permission.migration_path');
        if ($name) {
            $path .= '/' . $name;
        }
        return base_path($path);
    }

    protected function write($path, $items)
    {
        $path = Util::normalizePath($path);
        $fd = fopen($path, 'w+');
        fwrite($fd, $this->arrayToString($items));
        fclose($fd);
    }

    protected function arrayToString(array $array, int $index = 0, string $start = "<?php\n\nreturn ", string $end = ";\n")
    {
        $content = "{$start}[";

        if ($index >= 0) {
            $content .= "\n";
            $tab_str = str_pad('', $index + 4);
        } else {
            $tab_str = '';
        }

        $is_assoc = Arr::isAssoc($array);

        $len = count($array);

        $i = 0;

        foreach ($array as $key => $value) {
            $content .= $tab_str;
            if ($is_assoc) {
                $content .= "\"$key\" => ";
            }
            if ($value === null) {
                $content .= 'null';
            } elseif (is_string($value)) {
                $content .= "\"$value\"";
            } elseif (is_int($value)) {
                $content .= $value;
            } elseif (is_bool($value)) {
                $content .= $value ? 'true' : 'false';
            } elseif (is_array($value)) {
                $content .= $this->arrayToString($value, $index >= 0 ? $index + 4 : $index, '', '');
            }

            if ($i < $len - 1) {
                $content .= ',';
            }

            if ($index >= 0) $content .= "\n";

            $i++;
        }
        $content .= str_pad('', $index) . "]$end";
        return $content;
    }
}
