<?php

namespace YiluTech\Permission;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\File\File;


class MigrationBatch
{
    protected $files;

    public function __construct(array $files)
    {
        foreach ($files as $key => $file) {
            if (is_int($key)) {
                $key = $file instanceof File ? ($file instanceof UploadedFile ? $file->getClientOriginalName() : $file->getFilename()) : basename($file);
            }
            $this->files[$key] = $file;
        }
        ksort($this->files);
    }

    public function files()
    {
        return array_keys($this->files);
    }

    public function migrate($service)
    {
        \DB::transaction(function () use ($service) {
            $manager = new PermissionManager($service);
            foreach ($this->files as $file => $path) {
                foreach ($this->read($file) as $name => $item) {
                    switch ($item['action']) {
                        case 'create':
                            $manager->create($name, $item['value'], $item['date']);
                            break;
                        case 'update':
                            $manager->update($name, $item['value'], $item['date']);
                            break;
                        case 'delete':
                        default:
                            $manager->delete($name);
                            break;
                    }
                }
            }
            $migration = app(Migration::class, ['service' => $service]);
            $manager->save($migration->lastBatch());
            $migration->migrate($this->files());
        });
    }

    protected function read($file)
    {
        $content = file_get_contents($this->files[$file]);
        if (empty($content)) {
            throw new PermissionException(sprintf('file %s is empty', $file));
        }

        $type = $this->guessExtension($this->files[$file]);
        if ($type === 'yaml' && !function_exists('yaml_parse')) {
            throw new PermissionException('yaml extension not support.');
        }

        try {
            $content = $type === 'yaml' ? yaml_parse($content) : json_decode($content, JSON_OBJECT_AS_ARRAY);
        } catch (\Exception $exception) {
            throw new PermissionException(sprintf('parse file %s content error, please define json or yam file.', $file));
        }

        if (!is_array($content)) {
            throw new PermissionException(sprintf('file %s content type error', $file));
        }

        $stack = [];
        foreach ($content as $item) {
            [$name, $item] = $this->parseContent($file, $item);
            if (isset($stack[$name])) {
                throw new PermissionException(sprintf('file %s repeated defining name[%s]', $file, $name));
            }
            $stack[$name] = $item;
        }
        return $stack;
    }

    protected function guessExtension($file)
    {
        if ($file instanceof UploadedFile) {
            return $file->guessClientExtension();
        }
        if ($file instanceof File) {
            return $file->guessExtension();
        }
        $segments = explode('.', basename($file));
        return end($segments);
    }

//    protected function apply(array $origin, $changes)
//    {
//        foreach ($changes as $key => $items) {
//            if (!isset($origin[$key])) {
//                $origin[$key] = null;
//            }
//            foreach ($items as $change) {
//                switch ($change['action']) {
//                    case '|<':
//                    case '>|':
//                        if (isset($change['attr'])) {
//                            $value = data_get($origin[$key], $change['attr']);
//                            $this->dataMerge($value, $change['value']);
//                            data_set($origin[$key], $change['attr'], $value);
//                        } else {
//                            $this->dataMerge($origin[$key], $change['value']);
//                        }
//                        break;
//                    case '|>':
//                    case '<|':
//                        if (isset($change['attr'])) {
//                            $value = data_get($origin[$key], $change['attr']);
//                            $this->dataSplit($value, $change['value']);
//                            data_set($origin[$key], $change['attr'], $value);
//                        } else {
//                            $this->dataSplit($origin[$key], $change['value']);
//                        }
//                        break;
//                    default:
//                        if (isset($change['attr'])) {
//                            data_set($origin[$key], $change['attr'], $change['value']);
//                        } else {
//                            $origin[$key] = $change['value'];
//                        }
//                        break;
//                }
//            }
//        }
//        return $origin;
//    }

    protected function parseContent($file, $content)
    {
        if (!is_array($content)) {
            return [$content, ['action' => 'delete']];
        }

        if (empty($content['name'])) {
            throw new PermissionException(sprintf('invalid name in file %s', $file));
        }

        if (empty($content['action'])) {
            $content['action'] = 'create';
        }

        if ($content['action'] === 'update' && isset($content['from'])) {
            $name = $content['from'];
            unset($content['from']);
        } else {
            $name = $content['name'];
        }

        $date = $this->getFileTime($file);
        switch ($content['action']) {
            case 'update':
                if ($name === $content['name']) {
                    unset($content['name']);
                }
                $parsed = [];
                foreach ($content as $key => $value) {
                    if (!preg_match('/^([<>]?)([\\w.-]+)([<>]?)$/', $key, $matches)) {
                        throw new PermissionException(sprintf('invalid attribute %s in file %s', $key, $file));
                    }
                    $action = $matches[1] ? $matches[1] . '|' : $matches[3] ? '|' . $matches[3] : '&';
                    if (strpos($matches[2], '.')) {
                        [$property, $attr] = explode('.', $matches[2], 2);
                        $parsed[$property][] = compact('action', 'value', 'attr');
                    } else {
                        $parsed[$matches[2]][] = compact('action', 'value');
                    }
                }
                return [$name, ['action' => 'update', 'date' => $date, 'value' => Arr::only($parsed, ['name', 'type', 'scopes', 'content', 'translations'])]];
            case 'create':
                if (empty($content['type'])) {
                    throw new PermissionException(sprintf('permission type required, in file %s (name:%s) ', $file, $content['name']));
                }
                if (isset($content['scopes'])) {
                    if (!is_array($content['scopes'])) {
                        $content['scopes'] = [$content['scopes']];
                    }
                } else {
                    $content['scopes'] = [];
                }
                return [$name, ['action' => 'create', 'date' => $date, 'value' => Arr::only($content, ['name', 'type', 'scopes', 'content', 'translations'])]];
            case 'delete':
                return [$name, ['action' => 'delete', 'date' => $date]];
            default:
                throw new PermissionException(sprintf('Invalid action, in file %s (name:%s) ', $file, $content['name']));
        }
    }

    protected function getFileTime($file)
    {
        preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{6})/', basename($file), $matches);
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . implode(':', str_split($matches[4], 2));
    }

//    /**
//     * @param $stack array
//     * @param $file string
//     * @return array
//     * @throws PermissionException
//     */
//    protected function merge($stack, $file)
//    {
//        foreach ($this->read($file) as $name => $item) {
//            if (isset($stack[$name])) {
//                $action = $stack[$name]['action'];
//                switch ($item['action']) {
//                    case 'create':
//                        if ($action === 'delete') {
//                            $stack[$name] = array_merge($item, ['action' => 'update']);
//                        } else if ($action === 'update') {
//                            throw new PermissionException(sprintf('file %s name %s already exists, can not create.', $file, $name));
//                        } else {
//                            throw new PermissionException(sprintf('file %s name %s action error', $file, $name));
//                        }
//                        break;
//                    case 'delete':
//                        if ($action === 'create') {
//                            unset($stack[$name]);
//                        } else {
//                            $stack[$name] = $name;
//                        }
//                        break;
//                    default:
//                        if ($action === 'create') {
////                            $stack[$name] = array_merge($stack[$name], $item, compact('action'));
//                            $stack[$name] = $this->apply($stack[$name], $item['value']);
//                        } else if ($action === 'update') {
//                            if (isset($stack[$name]['from'])) {
//                                throw new PermissionException(sprintf('file %s name %s not found', $file, $name));
//                            }
//                            if (isset($name['from'])) {
//                                $stack[$item['name']] = compact('action');
//                            }
//                            $stack[$name] = array_merge(array_merge($stack[$name], $item));
//                        } else {
//                            throw new PermissionException(sprintf('file %s name %s action error', $file, $name));
//                        }
//                        break;
//                }
//            } else {
//                $stack[$name] = $item;
//            }
//        }
//        return $stack;
//    }

    protected function flip($stack)
    {

    }
}
