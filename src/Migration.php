<?php

namespace YiluTech\Permission;

use Illuminate\Support\Facades\DB;


class Migration
{
    protected $table = 'permission_migrations'; // services, migration, batch, group

    private $service;

    private $batches;

    public function __construct(string $service)
    {
        $this->service = $service;
        $this->batches = $this->query()->get()->groupBy('batch')->sortKeysDesc();
    }

    protected function query()
    {
        return DB::table($this->table)->where('service', $this->service);
    }

    public function exists($filename)
    {
        foreach ($this->batches as $batches) {
            if ($batches->firstWhere('migration', $filename)) {
                return true;
            }
        }
        return false;
    }

    public function batches()
    {
        return $this->batches->keys();
    }

    public function migrations()
    {
        return $this->batches->flatten()->pluck('migration');
    }

    public function lastBatch(): int
    {
        return $this->batches()->get(0, 0);
    }

    public function migrate(array $files)
    {
        $batch = $this->lastBatch() + 1;
        $container = collect();
        foreach ($files as $migration) {
            $content = ['service' => $this->service, 'migration' => $migration, 'batch' => $batch];
            $this->query()->insert($content);
            $container->put(null, (object)$content);
        }
        $this->batches->put($batch, $container)->sortKeysDesc();
        return $this;
    }

    public function rollback()
    {
        if ($batch = $this->lastBatch()) {
            $this->query()->where('batch', $batch)->delete();
            $this->batches->offsetUnset($batch);
        }
        return $this;
    }

    public function mergeTo($file)
    {
        $this->query()->delete();

        $this->batches = collect();
        return $this->migrate([$file]);
    }

    public function migrated($steps = 1)
    {
        if ($steps < 1) {
            return $this->batches->flatten()->pluck('migration');
        }
        $index = 0;
        $migrations = collect();
        foreach ($this->batches as $batch) {
            if ($index++ >= $steps) {
                break;
            }
            $migrations = $migrations->merge($batch->pluck('migration'));
        }
        return $migrations;
    }
}
