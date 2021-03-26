<?php

namespace JoeDixon\Translation;

use Illuminate\Filesystem\Filesystem;
use JoeDixon\Translation\Drivers\Database;
use JoeDixon\Translation\Drivers\File;

class TranslationManager
{
    private $app;
    /** @var mixed[] */
    private array $config;
    private Scanner $scanner;

    public function __construct($app, array $config, Scanner $scanner)
    {
        $this->app = $app;
        $this->config = $config;
        $this->scanner = $scanner;
    }

    public function resolve()
    {
        $driver = $this->config['driver'];

        if ($driver === 'file') {
            return $this->resolveFileDriver();
        }

        if ($driver === 'database') {
            return $this->resolveDatabaseDriver();
        }

        throw new \InvalidArgumentException("Invalid driver [$driver]");
    }

    protected function resolveFileDriver(): File
    {
        return new File(new Filesystem, $this->app['path.lang'], $this->app->config['app']['locale'], $this->scanner);
    }

    protected function resolveDatabaseDriver(): Database
    {
        return new Database($this->app->config['app']['locale'], $this->scanner, $this->app->cache);
    }
}
