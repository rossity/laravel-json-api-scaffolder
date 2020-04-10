<?php

namespace Rossity\LaravelApiScaffolder\Scaffolders;

use Illuminate\Support\Str;
use Nette\PhpGenerator\Parameter;
use Nette\PhpGenerator\PhpFile;
use Nette\PhpGenerator\PsrPrinter;

class PolicyScaffolder
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
        if (! is_dir(app_path('Policies'))) {
            mkdir(app_path('Policies'));
        }

        $file = new PhpFile();

        $namespace = $file->addNamespace('App\Policies');

        $namespace->addUse('App\User');
        $namespace->addUse('App\\'.$this->config['name']);
        $namespace->addUse('Illuminate\Auth\Access\Response');
        $namespace->addUse('Illuminate\Auth\Access\HandlesAuthorization');

        $class = $namespace->addClass($this->config['name'].'Policy');

        $class->addTrait('Illuminate\Auth\Access\HandlesAuthorization');

        $userParams = [
            (new Parameter('user'))->setType('App\User'),
        ];

        $modelCamel = Str::of($this->config['name'])->camel();
        $modelSnake = Str::of($this->config['name'])->snake();

        $userAndModelParams = [
            (new Parameter('user'))->setType('App\User'),
            (new Parameter($modelCamel))->setType('App\\'.$this->config['name']),
        ];

        $userModelCheck = "return \$user->is(\${$modelCamel}->user) ? Response::allow() : Response::deny('You do not own this $modelSnake.');";

        $class
            ->addMethod('viewAny')
            ->setBody('return true;')
            ->setParameters($userParams);

        $class
            ->addMethod('view')
            ->setBody('return true;')
            ->setParameters($userAndModelParams);

        $class
            ->addMethod('create')
            ->setBody('return true;')
            ->setParameters($userParams);

        $class
            ->addMethod('update')
            ->setBody($userModelCheck)
            ->setParameters($userAndModelParams);

        $class
            ->addMethod('delete')
            ->setBody($userModelCheck)
            ->setParameters($userAndModelParams);

        $class
            ->addMethod('restore')
            ->setBody($userModelCheck)
            ->setParameters($userAndModelParams);

        $class
            ->addMethod('forceDelete')
            ->setBody($userModelCheck)
            ->setParameters($userAndModelParams);

        $path = app_path('Policies/'.$this->config['name'].'Policy.php');

        $print = (new PsrPrinter())->printFile($file);

        file_put_contents($path, $print);

        return $path;
    }
}
