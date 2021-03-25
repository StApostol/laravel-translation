<?php

namespace JoeDixon\Translation;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use JoeDixon\Translation\Drivers\Database;
use JoeDixon\Translation\Drivers\File;

class TranslationManager
{
    private $app;
    private $config;
    private Scanner $scanner;

    public function __construct($app, $config, Scanner $scanner)
    {
        $this->app = $app;
        $this->config = $config;
        $this->scanner = $scanner;
    }

    public function resolve()
    {
        $driver = $this->config['driver'];
        $driverResolver = Str::studly($driver);
        $method = "resolve{$driverResolver}Driver";

        if (!method_exists($this, $method)) {
            throw new \InvalidArgumentException("Invalid driver [$driver]");
        }

        return $this->{$method}();
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
