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
        $this->batches = $this->query()->get()->groupBy('batch');
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

    public function lastBatch()
    {
        return $this->batches->keys()->last(null, 0);
    }

    public function migrate($files)
    {
        $batch = $this->lastBatch() + 1;
        $container = collect();
        foreach ($files as $migration) {
            $content = ['service' => $this->service, 'migration' => $migration, 'batch' => $batch];
            $this->query()->insert($content);
            $container->put(null, (object)$content);
        }
        $this->batches->put($batch, $container);
    }

    public function rollback()
    {
        if ($batch = $this->lastBatch()) {
            $this->query()->where('batch', $batch)->delete();
            return tap($this->batches[$batch], function ($container) use ($batch) {
                $this->batches->offsetUnset($batch);
            });
        }
        return collect();
    }

    public function migrated($times = 1)
    {
        if ($max = $this->lastBatch()) {
            $min = $times < 1 ? 0 : max($max - $times, 0);

            if ($min === 0) {
                return $this->batches->flatten();
            }

            return $this->batches->filter(function ($item, $batch) use ($min, $max) {
                return $batch > $min;
            })->flatten();
        }
        return collect();
    }
}
