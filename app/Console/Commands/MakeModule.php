<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeModule extends Command
{
    protected $signature = 'make:module {name : StudlyCase module name, e.g. CompanyManagement}';

    protected $description = 'Scaffold a self-contained module under Modules/ with a module.php manifest';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        $base = config('modules.path').'/'.$name;

        if (is_dir($base)) {
            $this->error("Module {$name} already exists.");

            return self::FAILURE;
        }

        $folders = [
            'Controllers',
            'Models',
            'Services',
            'Requests',
            'Policies',
            'Notifications',
            'Routes',
            'Database/Migrations',
            'Database/Seeders',
            'Resources/views',
            'Vue/Pages',
            'Vue/Components',
            'Tests',
        ];

        foreach ($folders as $folder) {
            mkdir("{$base}/{$folder}", 0755, true);
        }

        file_put_contents("{$base}/module.php", $this->manifestStub($name));
        file_put_contents("{$base}/Routes/web.php", "<?php\n\nuse Illuminate\\Support\\Facades\\Route;\n");

        $this->info("Module {$name} scaffolded at Modules/{$name}.");
        $this->line("Namespace: Modules\\{$name}\\...");

        return self::SUCCESS;
    }

    protected function manifestStub(string $name): string
    {
        return <<<PHP
<?php

return [

    'name' => '{$name}',

    'version' => '1.0.0',

    'enabled' => true,

    // Route files are discovered automatically at Routes/web.php and
    // Routes/api.php; override the paths here if needed.

    // Extra service providers to register for this module.
    'providers' => [],

];

PHP;
    }
}
