<?php

namespace Bastinald\LaravelAutomaticMigrations\Commands;

use Illuminate\Console\Command;
use Doctrine\DBAL\Schema\Comparator;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;
use Symfony\Component\Finder\Finder;

class MigrateAutoCommand extends Command
{
    protected $signature = 'migrate:auto {--f|--fresh} {--s|--seed} {--force} {--pretend}';

    public function handle()
    {
        if (app()->environment('production') && !$this->option('force')) {
            $this->warn('Use the <info>--force</info> to migrate in production.');

            return;
        }

        $this->handleTraditionalMigrations();
        $this->handleAutomaticMigrations();
        $this->seed();

        $this->info('Automatic migration completed successfully.');
    }

    private function handleTraditionalMigrations()
    {
        $command = 'migrate';

        if ($this->option('fresh')) {
            $command .= ':fresh';
        }

        if ($this->option('force')) {
            $command .= ' --force';
        }

        Artisan::call($command, [], $this->getOutput());
    }

    private function handleAutomaticMigrations()
    {
        $models = collect();

        $modelPaths = config('laravel-automatic-migrations.model_paths');
        foreach ($modelPaths as $namespace => $path) {
            if (!is_dir($path)) {
                continue;
            }

            foreach ((new Finder)->in($path) as $model) {
                $model = $namespace . str_replace(
                        ['/', '.php'],
                        ['\\', ''],
                        Str::after($model->getRealPath(), realpath($path))
                    );

                if (method_exists($model, 'migration')) {
                    $models->push([
                        'object' => $object = app($model),
                        'order' => $object->migrationOrder ?? 0,
                    ]);
                }
            }
        }

        foreach ($models->sortBy('order') as $model) {
            $this->migrate($model['object']);
        }
    }

    private function migrate($model)
    {
        $modelTable = $model->getTable();
        $tempTable = 'table_' . $modelTable;
        $pretend = $this->option('pretend');

        Schema::dropIfExists($tempTable);
        Schema::create($tempTable, function (Blueprint $table) use ($model) {
            $model->migration($table);
        });

        // alter existing table
        if (Schema::hasTable($modelTable)) {
            $schemaManager = $model->getConnection()->getDoctrineSchemaManager();
            $modelTableDetails = $schemaManager->listTableDetails($modelTable);
            $tempTableDetails = $schemaManager->listTableDetails($tempTable);
            $tableDiff = (new Comparator)->diffTable($modelTableDetails, $tempTableDetails);

            if ($tableDiff) {
                $queries = $schemaManager->getDatabasePlatform()->getAlterTableSQL($tableDiff);

                if ($pretend) {
                    foreach ($queries as $query) {
                        $this->line("<info>".get_class($model).":</info> {$query}");
                    }

                    return;
                }

                $schemaManager->alterTable($tableDiff);

                $this->line('<info>Table updated:</info> ' . $modelTable);
            }

            Schema::drop($tempTable);

            return;
        }

        // create new table
        $queries = Schema::getConnection()->pretend(function () use ($modelTable, $model) {
            Schema::create($modelTable, static function (Blueprint $table) use ($model) {
                $model->migration($table);
            });
        });

        if ($pretend) {
            foreach ($queries as $query) {
                $this->line("<info>".get_class($model).":</info> {$query['query']}");
            }

            return;
        }

        Schema::rename($tempTable, $modelTable);
        Schema::dropIfExists($tempTable);

        $this->line('<info>Table created:</info> ' . $modelTable);
    }

    private function seed()
    {
        if (!$this->option('seed')) {
            return;
        }

        $command = 'db:seed';

        if ($this->option('force')) {
            $command .= ' --force';
        }

        Artisan::call($command, [], $this->getOutput());
    }
}
