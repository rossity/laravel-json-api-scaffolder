<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class ControllerScaffolder
{
    private $config;

    private $camelName;

    public function __construct($config)
    {
        $this->config = $config;

        $this->camelName = Str::of($this->config['name'])->camel();
    }

    public function handle()
    {
        $file = new PhpFile();

        $namespace = $file->addNamespace('App\Http\Controllers');

        $namespace
            ->addUse('App\Http\Requests\\'.$this->config['name'].'StoreRequest')
            ->addUse('App\Http\Requests\\'.$this->config['name'].'UpdateRequest')
            ->addUse('App\Http\Resources\\'.$this->config['name'].'Collection')
            ->addUse('App\Http\Resources\\'.$this->config['name'].'Resource')
            ->addUse('Spatie\QueryBuilder\AllowedFilter')
            ->addUse('Spatie\QueryBuilder\QueryBuilder')
            ->addUse('Illuminate\Http\Response')
            ->addUse('Illuminate\Http\Request')
            ->addUse('App\\'.$this->config['name']);

        $class = $namespace->addClass($this->config['name'].'Controller')
            ->setExtends('App\Http\Controllers\Controller');

        $class->addMethod('__construct')
            ->setBody($this->getConstructorBody());

        $class->addMethod('index')
            ->setParameters([
                (new Parameter('request'))->setType('Illuminate\Http\Request'),
            ])
            ->setBody($this->getIndexBody());

        $class->addMethod('store')
            ->setParameters([
                (new Parameter('request'))->setType('App\Http\Requests\\'.$this->config['name'].'StoreRequest'),
            ])
            ->setBody($this->getStoreBody());

        $class->addMethod('show')
            ->setParameters([
                (new Parameter($this->camelName))->setType('App\\'.$this->config['name']),
            ])
            ->setBody($this->getShowBody());

        $class->addMethod('update')
            ->setParameters([
                (new Parameter('request'))->setType('App\Http\Requests\\'.$this->config['name'].'UpdateRequest'),
                (new Parameter($this->camelName))->setType('App\\'.$this->config['name']),
            ])
            ->setBody($this->getUpdateBody());

        $class->addMethod('destroy')
            ->setParameters([
                (new Parameter($this->camelName))->setType('App\\'.$this->config['name']),
            ])
            ->setBody($this->getDestroyBody());

        $path = app_path('Http/Controllers/'.$this->config['name'].'Controller.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getConstructorBody()
    {
        return '$this->authorizeResource('.$this->config['name'].'::class);';
    }

    private function getIndexBody()
    {
        $dumper = new Dumper();

        $pluralCamel = Str::of($this->camelName)->plural();

        $parts = [
            "\${$pluralCamel} = QueryBuilder::for({$this->config['name']}::class)",
        ];

        if ($rel = collect($this->config['relationships'])->get('User')) {
            if ($rel['type'] === 'belongsTo') {
                $parts[] = "\t->where('user_id', \$request->user()->id)";
            }
        }

        $parts = array_merge($parts, [
            "\t->allowedIncludes({$dumper->dump($this->getAllowedIncludes())})",
            "\t->allowedFilters({$dumper->dump($this->getAllowedFilters())})",
            "\t->allowedSorts({$dumper->dump($this->getAllowedSorts())})",
            "\t->jsonPaginate();",
            "\nreturn new {$this->config['name']}Collection(\${$pluralCamel});",
        ]);

        return implode("\n", $parts);
    }

    private function getAllowedIncludes()
    {
        return collect($this->config['relationships'])->map(function ($relationship, $name) {
            $name = Str::snake($name);

            if (in_array($relationship['type'], ['hasOne', 'belongsTo', 'hasOneThrough'])) {
                return $name;
            }

            return Str::plural($name);
        })->values()->toArray();
    }

    private function getAllowedFilters()
    {
        $relationshipsFields = collect($this->config['relationships'])
            ->filter(function ($relationship, $name) {
                return $relationship['type'] === 'belongsTo';
            })->map(function ($relationship, $name) {
                return new Literal('AllowedFilter::exact(\''.Str::of($name)->snake()->finish('_id').'\')');
            });

        $fields = collect($this->config['fields'])->map(function ($field, $name) {
            if (in_array($field['type'], ['boolean', 'integer', 'json'])) {
                return new Literal('AllowedFilter::exact(\''.$name.'\')');
            }

            return $name;
        });

        return $relationshipsFields->merge($fields)->values()->toArray();
    }

    private function getAllowedSorts()
    {
        return collect($this->config['fields'])->map(function ($field, $name) {
            return $name;
        })->values()->toArray();
    }

    private function getStoreBody()
    {
        $parts = [
            "\${$this->camelName} = new {$this->config['name']}();",
        ];

        if ($rel = collect($this->config['relationships'])->get('User')) {
            if ($rel['type'] === 'belongsTo') {
                $parts[] = "\${$this->camelName}->user()->associate(\$request->user());";
            }
        }

        $parts = array_merge($parts, [
            "\${$this->camelName}->fill(\$request->validated())->save();",
            "return response(new {$this->config['name']}Resource(\${$this->camelName}), Response::HTTP_CREATED);",
        ]);

        return implode("\n\n", $parts);
    }

    private function getShowBody()
    {
        return "response(new {$this->config['name']}Resource(\${$this->camelName}), Response::HTTP_OK);";
    }

    private function getUpdateBody()
    {
        $parts = [
            "\${$this->camelName}->update(\$request->validated());",
            "return response(new {$this->config['name']}Resource(\${$this->camelName}), Response::HTTP_OK);",
        ];

        return implode("\n\n", $parts);
    }

    private function getDestroyBody()
    {
        $parts = [
            "\${$this->camelName}->delete();",
            'return response(null, Response::HTTP_NO_CONTENT);',
        ];

        return implode("\n\n", $parts);
    }
}
