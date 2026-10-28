<?php

declare(strict_types=1);

use NetOs\Balena\Balena;
use NetOs\Balena\Data\Cast;
use NetOs\Balena\Data\Device;

function deviceVariableQuery(): array
{
    return app(Balena::class)->devices()->withVariables()->toQuery();
}

it('expands the environment, config and service variables', function () {
    $expand = (string) deviceVariableQuery()['$expand'];

    expect($expand)
        ->toContain('device_environment_variable($select=id,name,value)')
        ->toContain('device_config_variable($select=id,name,value)')
        ->toContain('device_service_environment_variable($select=id,name,value;$expand=service($select=id,service_name))');
});

it('includes the variables in withDetails() alongside the releases and tags', function () {
    $expand = (string) app(Balena::class)->devices()->withDetails()->toQuery()['$expand'];

    expect($expand)
        ->toContain('device_tag($select=tag_key,value)')
        ->toContain('device_environment_variable($select=')
        ->toContain('device_config_variable($select=')
        ->toContain('device_service_environment_variable($select=');
});

it('leaves the device fields unselected when only variables were asked for', function () {
    expect(deviceVariableQuery())->not->toHaveKey('$select');
});

it('reads environment and config variables as name-value maps', function () {
    $device = Device::fromArray([
        'uuid' => 'abc',
        'device_environment_variable' => [
            ['id' => 1, 'name' => 'API_URL', 'value' => 'https://example.test'],
            ['id' => 2, 'name' => 'LOG_LEVEL', 'value' => 'debug'],
        ],
        'device_config_variable' => [
            ['id' => 3, 'name' => 'BALENA_HOST_CONFIG_gpu_mem', 'value' => '128'],
        ],
    ]);

    expect($device->environmentVariables())
        ->toBe(['API_URL' => 'https://example.test', 'LOG_LEVEL' => 'debug'])
        ->and($device->environmentVariable('LOG_LEVEL'))->toBe('debug')
        ->and($device->environmentVariable('ABSENT'))->toBeNull()
        ->and($device->configVariable('BALENA_HOST_CONFIG_gpu_mem'))->toBe('128')
        ->and($device->configVariables())->toHaveCount(1);
});

it('keys service variables by service name when the service was expanded', function () {
    $device = Device::fromArray([
        'uuid' => 'abc',
        'device_service_environment_variable' => [
            ['name' => 'BROKER_URL', 'value' => 'tcp://localhost', 'service' => [['id' => 7, 'service_name' => 'mqtt']]],
            ['name' => 'QOS', 'value' => '1', 'service' => [['id' => 7, 'service_name' => 'mqtt']]],
            ['name' => 'PORT', 'value' => '8080', 'service' => [['id' => 9, 'service_name' => 'api']]],
        ],
    ]);

    expect($device->serviceVariables())->toBe([
        'mqtt' => ['BROKER_URL' => 'tcp://localhost', 'QOS' => '1'],
        'api' => ['PORT' => '8080'],
    ])
        ->and($device->serviceVariable('mqtt', 'QOS'))->toBe('1')
        ->and($device->serviceVariable('api', 'QOS'))->toBeNull();
});

it('falls back to the service id when the service link was not expanded', function () {
    $device = Device::fromArray([
        'device_service_environment_variable' => [
            ['name' => 'PORT', 'value' => '8080', 'service' => ['__id' => 9]],
        ],
    ]);

    expect($device->serviceVariables())->toBe(['9' => ['PORT' => '8080']]);
});

it('reports no variables when the relations were not expanded', function () {
    $device = Device::fromArray(['uuid' => 'abc']);

    expect($device->environmentVariables())->toBe([])
        ->and($device->configVariables())->toBe([])
        ->and($device->serviceVariables())->toBe([]);
});

it('reads the id of an unexpanded to-one link', function () {
    expect(Cast::relationId(['__id' => 42]))->toBe(42)
        ->and(Cast::relationId([['id' => 42]]))->toBe(42)
        ->and(Cast::relationId(42))->toBe(42)
        ->and(Cast::relationId(null))->toBeNull();
});
