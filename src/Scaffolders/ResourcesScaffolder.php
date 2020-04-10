<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class ResourcesScaffolder
{
    private $config;

    private $file;
    private $namespace;

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

        $this->namespace = $file->addNamespace('App\Http\Resources\\'.$this->config['name']);

        $use = $type === 'Resource' ? 'Illuminate\Http\Resources\Json\JsonResource' : 'Illuminate\Http\Resources\Json\ResourceCollection';

        $this->namespace->addUse($use);

        $name = $this->config['name'].$type;

        $class = $this->namespace->addClass($name);

        $class->setExtends($use);

        $dumper = new Dumper();

        $class
            ->addMethod('toArray')
            ->setBody('return '.$dumper->dump($this->getArray($type)).';')
            ->addParameter('request');

        $path = app_path('Http/Resources/'.$name.'.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getArray($type)
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

            $this->namespace->addUse("Http\Resources\\{$key}{$resource}");

            $fields->put(
                $name,
                new Literal("new {$key}$resource(\$this->whenLoaded('$name'))")
            );
        }

        $fields = $fields->merge([
            'created_at' => new Literal('$this->created_at'),
            'updated_at' => new Literal('$this->updated_at'),
        ])->toArray();

        if ($type === 'Resource') {
            return $fields;
        }

        return [
            'data' => $fields,
        ];
    }
}
