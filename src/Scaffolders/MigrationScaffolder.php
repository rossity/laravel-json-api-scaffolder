<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Closure;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class MigrationScaffolder
{
    private $config;

    /**
     * MigrationScaffolder constructor.
     * @param $config
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    public function handle()
    {
        $file = new PhpFile();

        $file
            ->addUse('Illuminate\Database\Migrations\Migration')
            ->addUse('Illuminate\Database\Schema\Blueprint')
            ->addUse('Illuminate\Support\Facades\Schema');

        $class = $file->addClass(
            'Create'
            .Str::of($this->config['name'])->pluralStudly().
            'Table')
            ->setExtends('Migration');

        $up = $class->addMethod('up');

        $closure = new Closure();

        $closure->addParameter('table')->setType('Blueprint');

        $closure->setBody($this->getFields());

        $table = Str::of($this->config['name'])->plural()->snake();

        $literal = "Schema::create('$table', $closure);";

        if ($pivots = collect($this->config['relationships'])->where('pivot', '=', true)) {
            foreach ($pivots as $name => $pivot) {
                $closure = new Closure();

                $closure->addParameter('table')->setType('Blueprint');

                $pivotTable = collect([$this->config['name'], $name])->sort()->map(function ($n) {
                    return Str::of($n)->snake()->__toString();
                });

                $pivotBody = [
                    "\$table->foreignId('{$pivotTable[0]}_id')->constrained()->onDelete('cascade');",
                    "\$table->foreignId('{$pivotTable[1]}_id')->constrained()->onDelete('cascade');",
                ];

                $closure->setBody(implode("\n", $pivotBody));

                $literal .= "\n\nSchema::create('{$pivotTable->implode('_')}', $closure);";
            }
        }

        $up->setBody($literal);

        $down = $class->addMethod('down');

        $literal = "Schema::dropIfExists('$table');";

        $down->setBody($literal);

        $path = database_path('migrations/'.date('Y_m_d_His').'_create_'.$table.'_table.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }

    private function getFields()
    {
        $body = [
            '$table->id();',
        ];

        foreach ($this->config['relationships'] as $key => $relationship) {
            if ($relationship['type'] == 'belongsTo') {
                $body[] =
                    '$table->foreignId(\''.
                    Str::of($key)->snake().
                    '_id\')->constrained()->onDelete(\'cascade\');';
            }
        }

        foreach ($this->config['fields'] as $key => $field) {
            $line = $this->getFieldType($key, $field);
            $line .= $field['nullable'] ? '->nullable();' : ';';
            $body[] = $line;
        }

        $body[] = '$table->timestamps();';

        return implode("\n", $body);
    }

    private function getFieldType($name, $field)
    {
        switch ($field['type']) {
            case 'bigInteger':
                return '$table->bigInteger(\''.$name.'\')';
            case 'boolean':
                return '$table->boolean(\''.$name.'\')->default(0)';
            case 'date':
                return '$table->date(\''.$name.'\', 0)';
            case 'dateTime':
                return '$table->dateTime(\''.$name.'\', 0)';
            case 'decimal':
                return '$table->decimal(\''.$name.'\', 8, 2)';
            case 'integer':
                return '$table->integer(\''.$name.'\')';
            case 'json':
                return '$table->json(\''.$name.'\')';
            case 'longText':
                return '$table->longText(\''.$name.'\')';
            case 'string':
                return '$table->string(\''.$name.'\')';
            case 'text':
                return '$table->text(\''.$name.'\')';
            case 'timestamp':
                return '$table->timestamp(\''.$name.'\')';
            case 'uuid':
                return '$table->uuid(\''.$name.'\')';
            default:
                return void;
        }
    }
}
