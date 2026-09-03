<?php

namespace NetOs\Balena\Tests;

use NetOs\Balena\BalenaServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            BalenaServiceProvider::class,
        ];
    }
}
