<?php

namespace Rossity\LaravelApiScaffolder\Console;

use Illuminate\Console\Command;

class ScaffoldApplication extends Command
{
    protected $signature = 'scaffold:application';

    protected $description = 'Scaffold the application based on model configuration files';

    public function handle()
    {
        $files = collect(array_diff(scandir(base_path('scaffolding')), ['..', '.']))
            ->map(function ($file) {
                return require base_path('scaffolding/'.$file);
            })
            ->sortBy('order');

        foreach ($files as $file) {
            foreach (array_filter($file['scaffolds']) as $scaffold => $create) {
                $this->info("Creating {$file['name']} $scaffold");

                $class = $this->getScaffoldClass($scaffold);

                $scaffolder = new $class($file);

                $scaffolder->handle();
            }
            if ($file['scaffolds']['migration']) {
                sleep(1);
            }
        }

        shell_exec('composer dump-autoload');
    }

    private function getScaffoldClass($name)
    {
        $name = ucfirst($name);

        return "Rossity\\LaravelApiScaffolder\\Scaffolders\\{$name}Scaffolder";
    }
}
