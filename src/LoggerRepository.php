<?php


namespace YiluTech\Permission;


class LoggerRepository
{
    private $path;

    private $filename = 'permission.log';

    public function __construct($path)
    {
        $this->setPath($path);
    }

    public function setPath($path)
    {
        $this->path = base_path(trim($path, '/'));
        return $this;
    }

    public function getPath($full = true)
    {
        if ($full) {
            return $this->path . '/' . $this->filename;
        }
        return $this->path;
    }

    public function read($start = null, $end = null)
    {
        $path = $this->getPath();

        $items = $changes = [];
        if (file_exists($path)) {
            $fs = fopen($path, 'r');
            while (!feof($fs)) {
                $row = trim(fgets($fs));
                if (!$row) continue;
                $item = $this->parseLine($row);

                if ($start && ($item['date'] < $start || ($item['date'] === $start && $start !== $end))) {
                    $this->mergeData($items, $item);
                    continue;
                }
                if ($end && $item['date'] > $end) break;
                $this->mergeAction($changes, $item);
            }
            fclose($fs);
        }
        return [$items, $changes];
    }

    public function write($changes)
    {
        if (empty($changes)) {
            return;
        }
        $date = date('Y-m-d H:i:s');
        $fs = fopen($this->getPath(), 'a');
        foreach ($changes as $key => $item) {
            fwrite($fs, $this->formatLine($date, $item['action'], $key, $item['data']));
        }
        fclose($fs);
    }

    protected function mergeData(&$items, $item)
    {
        if (isset($items[$item['name']])) {
            if ($item['action'] === 'DELETE') {
                unset($items[$item['name']]);
            } else {
                $items[$item['name']] = array_merge($items[$item['name']], $item['data']);
                $items[$item['name']]['date'] = $item['date'];
            }
        } elseif ($item['action'] !== 'DELETE') {
            $items[$item['name']] = $item['data'];
            $items[$item['name']]['date'] = $item['date'];
        }
    }

    protected function mergeAction(&$changes, $change)
    {
        if (isset($changes[$change['name']])) {
            $origin = &$changes[$change['name']];
            if ($change['action'] === 'UPDATE') {
                $origin['data'] = array_merge($origin['data'], $change['data']);
                $origin['date'] = $change['date'];
            } else {
                if ($origin['action'] === 'CREATE') {
                    unset($changes[$change['name']]);
                } else {
                    $origin = $change;
                }
            }
        } else {
            $changes[$change['name']] = $change;
        }
    }

    protected function formatLine($date, $action, $name, $data = null)
    {
        $str = "[$date] $action: $name";
        if ($data) {
            $str .= ' > ' . json_encode($data);
        }
        return $str . PHP_EOL;
    }

    protected function parseLine($str)
    {
        $item['date'] = substr($str, 1, 19);

        $parts = explode(':', substr($str, 21), 2);
        $item['action'] = trim($parts[0]);

        $parts = explode('>', $parts[1], 2);
        $item['name'] = trim($parts[0]);

        if (!empty($parts[1])) {
            $item['data'] = json_decode($parts[1], JSON_OBJECT_AS_ARRAY);
        }
        return $item;
    }
}
