<?php

namespace Rossity\LaravelApiScaffolder\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class ScaffoldApplication extends Command
{
    protected $signature = 'scaffold:application';

    protected $description = 'Scaffold the application based on model configuration files';

    public function handle()
    {
        $this->comment("Scaffolding application\n");

        $files = collect(array_diff(scandir(base_path('scaffolding')), ['..', '.']))
            ->map(function ($file) {
                return require base_path('scaffolding/'.$file);
            })
            ->sortBy('order');

        foreach ($files as $file) {
            $this->comment("Scaffolding {$file['name']}");
            foreach (array_filter($file['scaffolds']) as $scaffold => $create) {
                $this->info("Creating $scaffold");

                $class = $this->getScaffoldClass($scaffold);

                $scaffolder = new $class($file);

                $scaffolder->handle();
            }
            if ($file['scaffolds']['migration']) {
                sleep(1);
            }
            $this->line("\n");
        }

        $this->comment('Make sure to add the following routes to routes/api.php');

        $files->where('scaffolds.controller', true)->each(function ($file) {
            $name = Str::of($file['name'])->plural()->snake()->__toString();
            $this->info("Route::apiResource('{$name}', '{$file['name']}Controller');");
        });

        $this->line("\n");

        shell_exec('composer dump-autoload');
    }

    private function getScaffoldClass($name)
    {
        $name = ucfirst($name);

        return "Rossity\\LaravelApiScaffolder\\Scaffolders\\{$name}Scaffolder";
    }
}
