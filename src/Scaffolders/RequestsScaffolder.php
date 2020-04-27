<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class RequestsScaffolder
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
        if (! is_dir(app_path('Http/Requests'))) {
            mkdir(app_path('Http/Requests'));
        }

        return [
            $this->createFile('Store'),
            $this->createFile('Update'),
        ];
    }

    private function createFile($type)
    {
        $file = new PhpFile();

        $namespace = $file->addNamespace('App\Http\Requests');

        $namespace->addUse('Illuminate\Foundation\Http\FormRequest');

        $name = $this->config['name'].$type.'Request';

        $class = $namespace->addClass($name);

        $class->setExtends('Illuminate\Foundation\Http\FormRequest');

//        not needed since the default is to return true
//        $class
//            ->addMethod('authorize')
//            ->setBody('return true;');

        $dumper = new Dumper();
        $class
            ->addMethod('rules')
            ->setBody('return '.$dumper->dump($this->getRules($type)).';');

        $path = app_path('Http/Requests/'.$name.'.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getRules($type)
    {
        if ($type == 'Store') {
            return $this->getFieldRules($type)->merge($this->getRelatedModelRules())->toArray();
        }

        return $this->getFieldRules($type)->toArray();
    }

    private function getRelatedModelRules()
    {
        return collect($this->config['relationships'])
            ->where('type', '=', 'belongsTo')
            ->mapWithKeys(function ($relationship, $key) {
                return [
                    Str::of($key)->snake()->finish('_id')->__toString() => 'exists:'.Str::of($key)->snake()->plural()->__toString().',id',
                ];
            });
    }

    private function getFieldRules($type)
    {
        return collect($this->config['fields'])
            ->where('type', '!=', 'uuid')
            ->map(function ($field) use ($type) {
                $rules = [];

                if ($field['nullable']) {
                    $rules[] = 'nullable';
                } else {
                    if ($type == 'Store') {
                        $rules[] = 'required';
                    }
                }

                switch ($field['type']) {
                    case 'boolean':
                        $rules[] = 'boolean';
                        break;
                    case 'bigInteger':
                    case 'integer':
                        $rules[] = 'integer|numeric';
                        break;
                    case 'json':
                        $rules[] = 'array';
                        break;
                    case 'decimal':
                        $rules[] = 'numeric';
                        break;
                    case 'text':
                    case 'longtext':
                    case 'string':
                        $rules[] = 'string';
                        break;
                    case 'date':
                    case 'dateTime':
                        $rules[] = 'date';
                        break;
                    case 'uuid':
                        $rules[] = 'uuid';
                        break;
                    default:
                        $rules[] = '';
                }

                $rulesImploded = implode('|', $rules);

                if (Str::endsWith($rulesImploded, '|')) {
                    $rulesImploded = Str::replaceLast('|', '', $rulesImploded);
                }

                return $rulesImploded;
            });
    }
}
