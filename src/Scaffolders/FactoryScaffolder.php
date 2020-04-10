<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class FactoryScaffolder
{
    private $config;

    private $file;

    public function __construct($config)
    {
        $this->config = $config;

        $this->file = new PhpFile();
    }

    public function handle()
    {
        $this->file
            ->addUse('Faker\Generator', 'Faker')
            ->addUse('App\\'.$this->config['name']);

        $closure = new Closure();

        $closure->addParameter('faker')->setType('Faker');

        $dumper = new Dumper();

        $closure->setBody("return {$dumper->dump($this->getFields())};");

        $printer = new PsrPrinter();

        $factoryDefine = "\$factory->define({$this->config['name']}::class, {$printer->printClosure($closure)});";

        $closure = new Closure();

        $closure->addParameter(Str::of($this->config['name'])->camel())->setType($this->config['name']);

        $closure->addParameter('faker')->setType('Faker');

        $closure->setBody($this->getRunBody());

        $printer = new PsrPrinter();

        $factoryAfterCreating = "\$factory->afterCreating({$this->config['name']}::class, {$printer->printClosure($closure)});";

        $comment = '/* @var \Illuminate\Database\Eloquent\Factory $factory */';

        $path = database_path('factories/'.$this->config['name'].'Factory.php');

        file_put_contents(
            $path,
            $printer->printFile($this->file)."\n".$comment."\n\n".$factoryDefine."\n\n".$factoryAfterCreating
        );

        return $path;
    }

    private function getFields()
    {
        $body = collect();

        foreach ($this->config['relationships'] as $key => $relationship) {
            if ($relationship['type'] == 'belongsTo') {
                $this->file->addUse('App\\'.$key);
                $body[Str::of($key)->snake()->finish('_id')->__toString()] = "optional({$key}::inRandomOrder()->first())->id ?? factory({$key}::class)";
            }
        }

        foreach ($this->config['fields'] as $key => $field) {
            if ($field['nullable']) {
                $body[$key] = "\$faker->boolean(50) ? {$this->getFieldSeed($field)} : null";
            } else {
                $body[$key] = $this->getFieldSeed($field);
            }
        }

        return $body->mapWithKeys(function ($field, $key) {
            return [$key => new Literal($field)];
        })->toArray();
    }

    private function getFieldSeed($field)
    {
        switch ($field['type']) {
            case 'integer':
            case 'bigInteger':
                return '$faker->randomNumber';
            case 'boolean':
                return '$faker->boolean(50)';
            case 'date':
                return '$faker->date';
            case 'dateTime':
                return '$faker->dateTime';
            case 'decimal':
                return '$faker->randomFloat(2)';
            case 'json':
                return '[]';
            case 'longText':
                return '$faker->paragraphs(15, true)';
            case 'string':
                return '$faker->sentence';
            case 'text':
                return '$faker->paragraphs(5, true)';
            case 'timestamp':
                return '$faker->dateTimeBetween($startDate = \'-5 years\', $endDate = \'now\', \'UTC\')->format(\'Y-m-d H:i:s\')';
            case 'uuid':
                return '$faker->uuid';
            default:
                return void;
        }
    }

//    public function handle()
//    {
//        $this->file
//            ->addUse('Illuminate\Database\Seeder')
//            ->addUse("App\\{$this->config['name']}");
//
//        $class = $this->file->addClass($this->config['name'].'Seeder')
//            ->setExtends('Illuminate\Database\Seeder');
//
//        $class->addMethod('run')
//            ->setBody();
//
//        $path = database_path('seeds/'.$this->config['name'].'Seeder.php');
//
//        $print = (new PsrPrinter())->printFile($this->file);
//
//        file_put_contents($path, $print);
//
//        return $path;
//    }

    private function getRunBody()
    {
        if (count($this->config['relationships'])) {
            $camelName = Str::of($this->config['name'])->camel();

            $parts = [];

            foreach ($this->config['relationships'] as $name => $relationship) {
                if (in_array($relationship['type'], ['hasOne', 'hasOneThrough'])) {
                    $snake = Str::of($name)->snake();
                    $parts[] = "\${$camelName}->{$snake}()->save(factory({$name}::class)->create());";
                    $this->file->addUse("App\\{$name}");
                } else {
                    if (
                        in_array($relationship['type'], ['hasMany', 'hasManyThrough']) ||
                        ($relationship['type'] == 'belongsToMany' && isset($relationship['pivot']) && $relationship['pivot'])
                    ) {
                        $snakePlural = Str::of($name)->snake()->plural();
                        $parts[] = "\${$camelName}->{$snakePlural}()->saveMany(factory({$name}::class, mt_rand(1, 10))->create());";
                        $this->file->addUse("App\\{$name}");
                    }
                }
            }

            $parts = collect($parts)->map(function ($part) {
                return new Literal($part);
            })->toArray();

            return implode("\n", $parts);
        }

        return '';
    }

//    private function getClosureBody()
//    {
//        return collect($this->config['relationships'])->map(function ($relationship, $name) {
//            $name = Str::snake($name);
//
//            if (in_array($relationship['type'], ['hasOne', 'belongsTo', 'hasOneThrough'])) {
//                return $name;
//            }
//
//            return Str::plural($name);
//        })->values()->toArray();
//    }
}
