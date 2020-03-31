<?php


namespace YiluTech\Permission;


use YiluTech\Permission\Helper\Helper;

abstract class StoreAbstract
{

    /**
     * @var array
     *  [server, scopes, exclude]
     */
    protected $scopes;

    /**
     * @var LoggerRepository
     */
    protected $logger;

    public function __construct($logger, $scopes)
    {
        $this->logger = $logger;
        $this->scopes = $scopes;
    }

    public function serverScopeName()
    {
        return $this->scopes[0];
    }

    abstract public function getLastUpdateTime();

    abstract public function saveChanges($changes);

    public function sync($time = null)
    {
        $time = $time ?? $this->getLastUpdateTime();
        return $this->saveChanges($this->getChanges($time));
    }

    public function rollback($time = null)
    {
        $lastUpdateTime = $this->getLastUpdateTime();
        if (!$lastUpdateTime) {
            return 0;
        }
        $time = $time == -1 ? null : ($time ?: $lastUpdateTime);
        return $this->saveChanges($this->getChanges($time, $lastUpdateTime, true));
    }

    public function getChanges($start = null, $end = null, $flip = false)
    {
        [$items, $changes] = $this->logger->read($start, $end);

        if (empty($changes) || $this->scopes === '*') {
            return $changes;
        }

        if ($this->scopes !== '*') {
            $changes = array_filter(array_map(function ($item) use (&$items) {
                return $this->check($items[$item['name']] ?? null, $item);
            }, $changes));
        }

        if ($flip) {
            $changes = $this->flip($changes, $items);
        }

        return $changes;
    }

    public function exists($scopes)
    {
        return !empty(array_uintersect($this->scopes[1], $scopes, [Helper::class, 'scope_differ']));
    }

    protected function flip($changes, $items)
    {
        return array_map(function ($change) use ($items) {
            switch ($change['action']) {
                case 'CREATE':
                    return ['action' => 'DELETE'];
                case 'UPDATE':
                    $change['data'] = array_intersect_key($items[$change['name']], array_flip(array_keys($change['data'])));
                    $change['date'] = $items[$change['name']]['date'];
                    return $change;
                default:
                    $change['action'] = 'CREATE';
                    $change['data'] = $items[$change['name']];
                    $change['date'] = $change['data']['date'];
                    unset($change['data']['date']);
                    return $change;
            }
        }, $changes);
    }

    protected function check($item, $change)
    {
        if ($change['action'] === 'CREATE') {
            return $this->exists($change['data']['scopes']) ? $change : null;
        }

        if ($change['action'] === 'DELETE') {
            return $this->exists($item['scopes']) ? $change : null;
        }

        $newState = $this->exists($change['data']['scopes']);
        $oldState = $this->exists($item['scopes']);

        if ($oldState) {
            if (!$newState) {
                $change['action'] = 'DELETE';
                unset($change['data']);
            }
        } else {
            if ($newState) {
                unset($item['date']);
                $change['action'] = 'CREATE';
                $change['data'] = array_merge($item, $change['data']);
            } else {
                return null;
            }
        }
        return $change;
    }
}
