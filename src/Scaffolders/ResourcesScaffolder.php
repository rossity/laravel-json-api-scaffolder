<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class ResourcesScaffolder
{
    private $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    public function handle()
    {
        if (! is_dir(app_path('Http/Resources'))) {
            mkdir(app_path('Http/Resources'), 0777, true);
        }

        return [
            $this->createFile('Resource'),
            $this->createFile('Collection'),
        ];
    }

    private function createFile($type)
    {
        $file = new PhpFile();

        $namespace = $file->addNamespace('App\Http\Resources');

        $use = $type === 'Resource' ? 'Illuminate\Http\Resources\Json\JsonResource' : 'Illuminate\Http\Resources\Json\ResourceCollection';

        $namespace->addUse($use);

        if ($type == 'Collection') {
            $namespace->addUse("App\\{$this->config['name']}");
            $namespace->addUse("Illuminate\Support\Collection");
        }

        $name = $this->config['name'].$type;

        $class = $namespace->addClass($name);

        $class->setExtends($use);

        $dumper = new Dumper();

        $body = $type == 'Resource' ? $this->getResourceArray() : $this->getCollectionArray();

        $class
            ->addMethod('toArray')
            ->setBody('return '.$dumper->dump($body).';')
            ->addParameter('request');

        $path = app_path('Http/Resources/'.$name.'.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getResourceArray()
    {
        $fields = collect($this->config['fields'])
            ->map(function ($field, $key) {
                return new Literal('$this->'.$key);
            })
            ->prepend(new Literal('$this->id'), 'id');

        foreach ($this->config['relationships'] as $key => $relationship) {
            if (in_array($relationship['type'], ['hasOne', 'hasOneThrough', 'belongsTo'])) {
                $name = (string) Str::of($key)->snake();
                $resource = 'Resource';
            } else {
                $name = (string) Str::of($key)->plural()->snake();
                $resource = 'Collection';
            }

            $fields->put(
                $name,
                new Literal("new {$key}$resource(\$this->whenLoaded('$name'))")
            );
        }

        $fields = $fields->merge([
            'created_at' => new Literal('$this->created_at'),
            'updated_at' => new Literal('$this->updated_at'),
        ])->toArray();

        return $fields;
    }

    private function getCollectionArray()
    {
        $camelName = Str::of($this->config['name'])->camel()->__toString();

        $closure = new Closure();

        $closure->addParameter($camelName)->setType("{$this->config['name']}");

        $printer = new PsrPrinter();

        $conditionals = [];

        foreach ($this->config['relationships'] as $key => $relationship) {
            if (in_array($relationship['type'], ['hasOne', 'hasOneThrough', 'belongsTo'])) {
                $name = (string) Str::of($key)->snake();
                $resource = 'Resource';
            } else {
                $name = (string) Str::of($key)->plural()->snake();
                $resource = 'Collection';
            }

            $conditionals[] = new Literal("if (\${$camelName}->relationLoaded('$name')) {\n   \${$camelName}['$name'] = new {$key}$resource(\${$camelName}->$name);\n}");
        }

        $conditionals = implode("\n\n", $conditionals);

        $closure->setBody("{$conditionals}\n\nreturn \${$camelName}->getAttributes();");

        return new Literal("\$this->collection->transform({$printer->printClosure($closure)})");
    }
}
