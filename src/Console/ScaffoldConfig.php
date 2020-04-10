<?php

namespace Rossity\LaravelApiScaffolder\Console;

use Illuminate\Console\Command;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpFile;

class ScaffoldConfig extends Command
{
    protected $signature = 'scaffold:config {model}';

    protected $description = 'Scaffold the configuration for a model';

    public function handle()
    {
        $this->info('Creating '.$this->argument('model').' model');

        if (! is_dir(base_path('scaffolding'))) {
            mkdir(base_path('scaffolding'));
        }

        $file = new PhpFile;

        $config = [
            'name' => $this->argument('model'),
            'order' => count(array_diff(scandir(base_path('scaffolding')), ['..', '.'])),
            'fields' => [],
            'relationships' => [],
            'scaffolds' => [
                'model' => false,
                'migration' => false,
                'controller' => false,
                'resources' => false,
                'requests' => false,
                'policy' => false,
//                'seeder' => false,
                'factory' => false,
            ],
        ];

        if ($this->confirm('Add fields?')) {
            do {
                $field = $this->ask('What is the name of the field?');
                $config['fields'][$field] = [
                    'type' => $this->choice('What type of field is '.$field.'?', [
                        'bigInteger',
                        'boolean',
                        'date',
                        'dateTime',
                        'decimal',
                        'integer',
                        'json',
                        'longText',
                        'string',
                        'text',
                        'timestamp',
                        'uuid',
                    ]),
                    'nullable' => $this->confirm('Is it nullable?', 'yes'),
                ];
            } while ($this->confirm('Add another field?', 'yes'));
        }

        if ($this->confirm('Add relationships?')) {
            do {
                $model = $this->ask('What is the name of the related model?');
                $config['relationships'][$model] = [
                    'type' => $this->choice('How is '.$this->argument('model').' related to '.$model.'?', [
                        'hasOne',
                        'hasMany',
                        'belongsTo',
                        'belongsToMany',
                        'hasOneThrough',
                        'hasManyThrough',
                    ]),
                ];

                if (in_array($config['relationships'][$model]['type'], ['hasOneThrough', 'hasManyThrough'])) {
                    $config['relationships'][$model]['through'] = $this->ask('Through what model is '.$model.' related?');
                } else {
                    if ($config['relationships'][$model]['type'] === 'belongsToMany') {
                        $config['relationships'][$model]['pivot'] = $this->confirm(
                            'Add pivot table?',
                            'no'
                        );
                    }
                }
            } while ($this->confirm('Add another relationship?', 'yes'));
        }

        if ($config['scaffolds']['model'] = $this->confirm('Add model?', 'yes')) {
        }

        if ($config['scaffolds']['migration'] = $this->confirm('Add migration?', 'yes')) {
        }

        if ($config['scaffolds']['controller'] = $this->confirm('Add controller?', 'yes')) {
            $config['scaffolds']['resources'] = true;
            $config['scaffolds']['requests'] = true;
            $config['scaffolds']['policy'] = true;
//            if ($config['scaffolds']['resources'] = $this->confirm('Add resources?', 'yes')) {
//            }
//
//            if ($config['scaffolds']['requests'] = $this->confirm('Add requests?', 'yes')) {
//            }
//
//            if ($config['scaffolds']['policy'] = $this->confirm('Add policy?', 'yes')) {
//            }
        }

        if ($config['scaffolds']['factory'] = $this->confirm('Add factory?', 'yes')) {
        }

        $dumper = new Dumper();

        $path = base_path('scaffolding/'.$this->argument('model').'.php');

        file_put_contents($path, "<?php \n\n return ".$dumper->dump($config).';');

        $this->info('Config file for '.$this->argument('model').' created.');
    }
}
