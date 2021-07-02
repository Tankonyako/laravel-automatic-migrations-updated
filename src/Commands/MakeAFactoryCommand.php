<?php

namespace Bastinald\LaravelAutomaticMigrations\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Livewire\Commands\ComponentParser;

class MakeAFactoryCommand extends Command
{
    protected $signature = 'make:afactory {class} {--force}';
    private $filesystem;
    private $modelParser;
    private $factoryParser;

    public function handle()
    {
        $this->filesystem = new Filesystem;

        $this->modelParser = new ComponentParser(
            is_dir(app_path('Models')) ? 'App\\Models' : 'App',
            config('livewire.view_path'),
            $modelClass = Str::replaceLast('Factory', '', $this->argument('class'))
        );

        $this->factoryParser = new ComponentParser(
            'Database\\Factories',
            config('livewire.view_path'),
            $modelClass . 'Factory'
        );

        if ($this->filesystem->exists($this->replacePath('classPath')) && !$this->option('force')) {
            $this->warn('Factory exists: <info>' . $this->replacePath('relativeClassPath') . '</info>');
            $this->warn('Use the <info>--force</info> to overwrite it.');

            return;
        }

        $this->makeStub();
        $this->makeModel();

        $this->warn('Factory made: <info>' . $this->replacePath('relativeClassPath') . '</info>');
    }

    private function replacePath($method)
    {
        return Str::replaceFirst(
            'app/Database/Factories',
            'database/factories',
            $this->factoryParser->$method()
        );
    }

    private function makeStub()
    {
        $replaces = [
            'DummyFactoryClass' => $this->factoryParser->className(),
            'DummyFactoryNamespace' => $this->factoryParser->classNamespace(),
            'DummyModelClass' => $this->modelParser->className(),
            'DummyModelNamespace' => $this->modelParser->classNamespace(),
        ];
        $stub = $this->modelParser->className() == 'User' ? 'UserFactory.php' : 'Factory.php';

        $contents = str_replace(
            array_keys($replaces),
            $replaces,
            $this->filesystem->get(config('laravel-automatic-migrations.stub_path') . '/' . $stub)
        );

        $this->filesystem->put($this->replacePath('classPath'), $contents);
    }

    private function makeModel()
    {
        Artisan::call('make:amodel', [
            'class' => $this->modelParser->className(),
            '--force' => $this->option('force'),
        ], $this->getOutput());
    }
}
