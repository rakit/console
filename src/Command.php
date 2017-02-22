<?php

namespace Rakit\Console;

abstract class Command
{

    protected $app;

    protected $signature;

    protected $description;

    public function getSignature()
    {
        return $this->signature;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function defineApp(App $app)
    {
        if (!$this->app) {
            $this->app = $app;
        }
    }

    public function __call($method, $args)
    {
        if ($this->app AND method_exists($this->app, $method)) {
            return call_user_func_array([$this->app, $method], $args);
        } else {
            $class = get_class($this);
            throw new \Exception("Call to undefined method {$class}::{$method}");
        }
    }

}
