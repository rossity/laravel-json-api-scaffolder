<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PhpLiteral;
use Nette\PhpGenerator\PhpNamespace;
use Nette\PhpGenerator\PsrPrinter;

class FactoryScaffolder
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

        $this->namespace = $file->addNamespace('Database\Factories');

        $this->namespace
            ->addUse('App\\Models\\'.$this->config['name'])
            ->addUse('Illuminate\Database\Eloquent\Factories\Factory');

        $class = new ClassType($this->config['name'].'Factory', new PhpNamespace('Database\Factories'));

        $class->setExtends('Illuminate\Database\Eloquent\Factories\Factory');

        $class->addProperty('model', new PhpLiteral($this->config['name'].'::class'))->setProtected();

        $dumper = new Dumper();

        $class->addMethod('definition')
            ->setBody("return {$dumper->dump($this->getFields())};");

//        $this->file
//            ->addUse('Faker\Generator', 'Faker')
//            ->addUse('App\\Models\\'.$this->config['name']);
//
//        $closure = new Closure();
//
//        $closure->addParameter('faker')->setType('Faker');
//
//        $closure->setBody();
//
//        $printer = new PsrPrinter();
//
//        $factoryDefine = "\$factory->define({$this->config['name']}::class, {$printer->printClosure($closure)});";
//
        $closure = new Closure();

        $closure->addParameter(Str::of($this->config['name'])->camel())->setType($this->config['name']);

//        $closure->addParameter('faker')->setType('Faker');

        $closure->setBody($this->getRunBody());
//
        $printer = new PsrPrinter();
//
        $factoryAfterCreating = "\$this->afterCreating({$printer->printClosure($closure)})";

        $class->addMethod('configure')
            ->setBody("return {$factoryAfterCreating};");

        $this->namespace->add($class);
//
//        $comment = '/* @var \Illuminate\Database\Eloquent\Factory $factory */';

        $path = database_path('factories/'.$this->config['name'].'Factory.php');

        file_put_contents(
            $path,
            $printer->printFile($file)
        );

        return $path;
    }

    private function getFields()
    {
        $body = collect();

        foreach ($this->config['relationships'] as $key => $relationship) {
            if ($relationship['type'] == 'belongsTo') {
                $this->namespace->addUse('App\\Models\\'.$key);
                $body[Str::of($key)->snake()->finish('_id')->__toString()] = "optional({$key}::inRandomOrder()->first())->id ?? {$key}::factory()";
            }
        }

        foreach ($this->config['fields'] as $key => $field) {
            if ($field['nullable']) {
                $body[$key] = "\$this->faker->boolean(50) ? {$this->getFieldSeed($field)} : null";
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
                return '$this->faker->randomNumber';
            case 'boolean':
                return '$this->faker->boolean(50)';
            case 'date':
                return '$this->faker->date';
            case 'dateTime':
                return '$this->faker->dateTime';
            case 'decimal':
                return '$this->faker->randomFloat(2)';
            case 'json':
                return '[]';
            case 'longText':
                return '$this->faker->paragraphs(15, true)';
            case 'string':
                return '$this->faker->sentence';
            case 'text':
                return '$this->faker->paragraphs(5, true)';
            case 'timestamp':
                return '$this->faker->dateTimeBetween($startDate = \'-5 years\', $endDate = \'now\', \'UTC\')->format(\'Y-m-d H:i:s\')';
            case 'uuid':
                return '$this->faker->uuid';
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
                    $parts[] = "\${$camelName}->{$snake}()->save({$name}::factory()->create());";
                    $this->namespace->addUse("App\\Models\\{$name}");
                } else {
                    if (
                        in_array($relationship['type'], ['hasMany', 'hasManyThrough']) ||
                        ($relationship['type'] == 'belongsToMany' && isset($relationship['pivot']) && $relationship['pivot'])
                    ) {
                        $snakePlural = Str::of($name)->snake()->plural();
                        $parts[] = "\${$camelName}->{$snakePlural}()->saveMany({$name}::factory()->count(mt_rand(1, 10))->create());";
                        $this->namespace->addUse("App\\Models\\{$name}");
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
