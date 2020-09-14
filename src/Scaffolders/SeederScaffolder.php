<?php

/*
 * Seeder scaffolder is deprecated but kept here for future reference
 * */

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class SeederScaffolder
{
    private $config;

    private $namespace;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function handle()
    {
        $file = new PhpFile();

        $this->namespace = $this->file->addNamespace('Database\Seeders');

        $this->namespace
            ->addUse('Illuminate\Database\Seeder')
            ->addUse("App\\Models\\{$this->config['name']}");


        $class = $this->namespace->addClass($this->config['name'].'Seeder')
            ->setExtends('Illuminate\Database\Seeder');

        $class->addMethod('run')
            ->setBody($this->getRunBody());

        $path = database_path('seeds/'.$this->config['name'].'Seeder.php');

        $print = (new PsrPrinter())->printFile($this->file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getRunBody()
    {
        $factory = "factory({$this->config['name']}::class, mt_rand(10, 50))->create()";

        if (count($this->config['relationships'])) {
            $camelName = Str::of($this->config['name'])->camel();
            $closure = new Closure();
            $closure->addParameter($camelName);

            $parts = [];
            foreach ($this->config['relationships'] as $name => $relationship) {
                if (in_array($relationship['type'], ['hasOne', 'hasOneThrough'])) {
                    $snake = Str::of($name)->snake();
                    $parts[] = "\${$camelName}->{$snake}()->save(factory({$name}::class)->make());";
                    $this->namespace->addUse("App\\{$name}");
                } else {
                    if (
                        in_array($relationship['type'], ['hasMany', 'hasManyThrough']) ||
                        ($relationship['type'] == 'belongsToMany' && isset($relationship['pivot']) && $relationship['pivot'])
                    ) {
                        $snakePlural = Str::of($name)->snake()->plural();
                        $parts[] = "\${$camelName}->{$snakePlural}()->saveMany(factory({$name}::class, mt_rand(1, 10))->make());";
                        $this->namespace->addUse("App\\{$name}");
                    }
                }
            }

            $parts = collect($parts)->map(function ($part) {
                return new Literal($part);
            })->toArray();

            $closure->setBody(implode("\n", $parts));

            $factory .= '->each('.$closure.')';
        }

        return $factory.';';
    }

    private function getClosureBody()
    {
        return collect($this->config['relationships'])->map(function ($relationship, $name) {
            $name = Str::snake($name);

            if (in_array($relationship['type'], ['hasOne', 'belongsTo', 'hasOneThrough'])) {
                return $name;
            }

            return Str::plural($name);
        })->values()->toArray();
    }
}
