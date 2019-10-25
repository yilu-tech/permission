<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Arr;
use YiluTech\Permission\PermissionManager;

class BasePermissionCommand extends Command
{
    /**
     * @var Filesystem
     */
    protected $file;

    protected $manager;

    public function __construct(Filesystem $filesystem, PermissionManager $permissionManager)
    {
        parent::__construct();

        $this->file = $filesystem;

        $this->manager = $permissionManager;
    }

    protected function write($changes, $items = null)
    {
        if ($items === null) {
            $items = $this->manager->all();
        }
        $path = $this->option('path');
        if (empty($changes)) {
            $this->file->delete($this->manager->getChangesFilePath($path));
        } else {
            $this->writeToFile($this->manager->getChangesFilePath($path), $changes);
        }
        $this->writeToFile($this->manager->getStoredFilePath($path), $items);
    }

    protected function writeToFile($path, $items)
    {
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
