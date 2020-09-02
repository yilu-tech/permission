<?php


namespace YiluTech\Permission\Commands;


use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use YiluTech\Permission\PermissionManager;
use YiluTech\Permission\RoutePermission;

class MakePermissionTranslationCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:permission-translation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'generate permission translation file.';

    /**
     * Execute the console command.
     *
     * @return void
     */
    public function handle()
    {
        $permissions = RoutePermission::all();
        $names = Arr::pluck($permissions, 'name');
        sort($names);

        $lang = app()->config['permission']['lang'] ?? [];

        foreach ($lang as $item) {
            $this->saveTranslation($item, $names);
        }

    }

    protected function saveTranslation($lang, $names)
    {
        $filename = "lang/$lang/permission.php";

        $path = resource_path($filename);
        if (file_exists($path)) {
            $original = require $path;
            $translations = [];
            foreach ($names as $name) {
                $translations[$name] = $original[$name] ?? [];
            }
        } else {
            $translations = array_fill_keys($names, []);
        }

        $export = var_export($translations, TRUE);
        $export = preg_replace("/^([ ]*)(.*)/m", '$1$1$2', $export);
        $array = preg_split("/\r\n|\n|\r/", $export);

        $array = preg_replace(["/\s*array\s\($/", "/\)(,)?$/", "/\s=>\s$/"], [NULL, ']$1', ' => ['], $array);
        $export = join(PHP_EOL, array_filter(["["] + $array));

        file_put_contents($path, '<?php return ' . $export . ';');

        $this->info("文件 $filename 已生成");
    }
}
