<?php

namespace YiluTech\Permission;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\File\File;


class MigrationBatch
{
    protected $files = [];

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
            tap(new PermissionManager($service), [$this, 'applyChanges'])->save($this->files());
        });
    }

    public function getChanges($service): array
    {
        $manager = new PermissionManager($service);
        $this->applyChanges($manager);
        $changes = [];
        foreach ($manager->getChanges() as [$action, $item]) {
            if ($action === 'delete') {
                $changes[$item->name] = [$action, null];
            } else if ($action === 'create') {
                $changes[$item->name] = [$action, $item->getAttributes()];
            } else if (!empty($dirty = $item->getDirty())) {
                $changes[$item->getOriginal('name')] = [$action, $dirty];
            }
        }
        return $changes;
    }

    public function applyChanges(PermissionCollection $collection)
    {
        foreach ($this->files as $file => $path) {
            [$date, $changes] = $this->read($file);
            try {
                foreach ($changes as [$name, $action, $change]) {
                    switch ($action) {
                        case 'create':
                            $collection->create($name, $change, $date);
                            break;
                        case 'update':
                            $collection->update($name, $change, $date);
                            break;
                        case 'delete':
                        default:
                            $collection->delete($name);
                            break;
                    }
                }
            } catch (PermissionException $exception) {
                throw new PermissionException("file[$file]: " . $exception->getMessage());
            }
        }
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

        try {
            $changes = [];
            foreach ($content as $name => $item) {
                $changes[] = $this->parseContent($name, $item);
            }
            return [$this->getFileTime($file), $changes];
        } catch (PermissionException $exception) {
            throw new PermissionException("file[$file]: " . $exception->getMessage());
        }
    }

    protected function parseContent($name, $content)
    {
        if ($update = $name[0] === '@') {
            $name = substr($name, 1);
        }
        if (is_string($content)) {
            return [$name, 'update', ['name' => $content]];
        }
        if (empty($content)) {
            return [$name, 'delete', null];
        }
        if (!is_array($content)) {
            throw new PermissionException(sprintf('invalid name[%s] content', $name));
        }
        if ($update) {
            $parsed = [];
            foreach ($content as $key => $value) {
                if (!preg_match('/^([<>]?)([\\w.-]+)([<>]?)$/', $key, $matches)) {
                    throw new PermissionException(sprintf('invalid name[%s] attribute %s', $name, $key));
                }
                $action = $matches[1] . '|' . $matches[3];
                if (strpos($matches[2], '.')) {
                    [$property, $attr] = explode('.', $matches[2], 2);
                    $parsed[$property][] = compact('action', 'value', 'attr');
                } else {
                    $parsed[$matches[2]][] = compact('action', 'value');
                }
            }
            return [$name, 'update', Arr::only($parsed, ['name', 'type', 'scopes', 'content', 'translations'])];
        }

        if (empty($content['type'])) {
            throw new PermissionException(sprintf('name[%s] type required', $name));
        }
        if (isset($content['scopes'])) {
            if (!is_array($content['scopes'])) {
                $content['scopes'] = [$content['scopes']];
            }
        } else {
            $content['scopes'] = [];
        }
        $content['name'] = $name;
        return [$name, 'create', Arr::only($content, ['name', 'type', 'scopes', 'content', 'translations'])];
    }

    protected function getFileTime($file)
    {
        preg_match('/^(\d{4})_(\d{2})_(\d{2})_(\d{6})/', basename($file), $matches);
        return $matches[1] . '-' . $matches[2] . '-' . $matches[3] . ' ' . implode(':', str_split($matches[4], 2));
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
}
