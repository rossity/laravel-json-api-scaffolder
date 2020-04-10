<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class ModelScaffolder
{
    private $config;

    /**
     * ModelScaffolder constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function handle()
    {
        $file = new PhpFile();

        $namespace = $file->addNamespace('App');

        $namespace->addUse('Illuminate\Database\Eloquent\Model');

        $class = $namespace->addClass($this->config['name']);

        $class->setExtends('Illuminate\Database\Eloquent\Model');

        $class->addProperty('fillable', $this->getFillable())->setProtected();

        $class->addProperty('casts', $this->getCasts())->setProtected();

        foreach ($this->config['relationships'] as $key => $relationship) {
            $class
                ->addMethod($this->getRelationshipMethodName($key, $relationship))
                ->setBody($this->getRelationshipMethodBody($key, $relationship));
        }

        $path = app_path($this->config['name'].'.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getRelationshipMethodName($name, $relationship)
    {
        $name = Str::of($name);

        if (in_array($relationship['type'], ['hasOne', 'belongsTo', 'hasOneThrough'])) {
            return $name->snake();
        }

        return $name->plural()->snake();
    }

    private function getRelationshipMethodBody($name, $relationship)
    {
        if (in_array($relationship['type'], ['hasOneThrough', 'hasManyThrough'])) {
            $rel = $name.'::class,'.$relationship['through'].'::class';
        } else {
            $rel = $name.'::class';
        }

        return 'return $this->'.$relationship['type'].'('.$rel.');';
    }

    public function getFillable()
    {
        return array_keys($this->config['fields']);
    }

    public function getCasts()
    {
        return collect($this->config['fields'])
            ->where('type', '!=', 'uuid')
            ->map(function ($field) {
                switch ($field['type']) {
                    case 'bigInteger':
                        return 'integer';
                    case 'json':
                        return 'array';
                    case 'decimal':
                        return 'decimal:2';
                    case 'text':
                    case 'longtext':
                        return 'string';
                    case 'dateTime':
                        return 'dateTime';
                    default:
                        return $field['type'];
                }
            })->toArray();
    }
}
