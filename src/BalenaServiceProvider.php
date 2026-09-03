<?php

declare(strict_types=1);

namespace NetOs\Balena;

use Illuminate\Http\Client\Factory;
use NetOs\Balena\Http\BalenaClient;
use NetOs\Balena\Support\ActionFactory;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class BalenaServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('balena')
            ->hasConfigFile();
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(BalenaClient::class, function ($app): BalenaClient {
            /** @var array<string, mixed> $config */
            $config = $app['config']->get('balena', []);

            /** @var array{times?: int} $retry */
            $retry = is_array($config['retry'] ?? null) ? $config['retry'] : [];

            return new BalenaClient(
                $app->make(Factory::class),
                (string) ($config['token'] ?? ''),
                (string) ($config['base_url'] ?? 'https://api.balena-cloud.com'),
                (string) ($config['version'] ?? 'v7'),
                (int) ($config['timeout'] ?? 15),
                (int) ($retry['times'] ?? 3),
            );
        });

        $this->app->singleton(ActionFactory::class);

        $this->app->singleton(Balena::class);
    }
}
