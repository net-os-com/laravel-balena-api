<?php

declare(strict_types=1);

use NetOs\Balena\Balena;
use NetOs\Balena\Data\Cast;
use NetOs\Balena\Data\Device;

function deviceVariableQuery(): array
{
    return app(Balena::class)->devices()->withVariables()->toQuery();
}

it('expands the environment and config variables and the service installs', function () {
    $expand = (string) deviceVariableQuery()['$expand'];

    expect($expand)
        ->toContain('device_environment_variable($select=id,name,value)')
        ->toContain('device_config_variable($select=id,name,value)')
        ->toContain('service_install($select=id;$expand=installs__service($select=id,service_name),device_service_environment_variable($select=id,name,value))');
});

/**
 * The relations an $expand asks for at the top level, ignoring whatever is
 * nested inside their own parentheses.
 *
 * @return list<string>
 */
function topLevelExpands(string $expand): array
{
    $names = [];
    $current = '';
    $depth = 0;

    foreach (str_split($expand) as $character) {
        if ($character === ',' && $depth === 0) {
            $names[] = $current;
            $current = '';

            continue;
        }

        $depth += match ($character) {
            '(' => 1,
            ')' => -1,
            default => 0,
        };

        $current .= $character;
    }

    $names[] = $current;

    return array_map(fn (string $name): string => explode('(', $name)[0], $names);
}

it('never expands service variables straight off the device', function () {
    // balena has no such relation: service variables belong to a service
    // install. An unknown navigation comes back as a 401 with an empty body
    // rather than a 400, so this regression reads like a bad token.
    expect(topLevelExpands((string) deviceVariableQuery()['$expand']))
        ->toBe(['device_environment_variable', 'device_config_variable', 'service_install']);
});

it('includes the variables in withDetails() alongside the releases and tags', function () {
    $expand = (string) app(Balena::class)->devices()->withDetails()->toQuery()['$expand'];

    expect($expand)
        ->toContain('device_tag($select=tag_key,value)')
        ->toContain('device_environment_variable($select=')
        ->toContain('device_config_variable($select=')
        ->toContain('service_install($select=');
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

it('keys service variables by service name when the install carries one', function () {
    $device = Device::fromArray([
        'uuid' => 'abc',
        'service_install' => [
            [
                'id' => 1,
                'installs__service' => [['id' => 7, 'service_name' => 'mqtt']],
                'device_service_environment_variable' => [
                    ['name' => 'BROKER_URL', 'value' => 'tcp://localhost'],
                    ['name' => 'QOS', 'value' => '1'],
                ],
            ],
            [
                'id' => 2,
                'installs__service' => [['id' => 9, 'service_name' => 'api']],
                'device_service_environment_variable' => [
                    ['name' => 'PORT', 'value' => '8080'],
                ],
            ],
        ],
    ]);

    expect($device->serviceVariables())->toBe([
        'mqtt' => ['BROKER_URL' => 'tcp://localhost', 'QOS' => '1'],
        'api' => ['PORT' => '8080'],
    ])
        ->and($device->serviceVariable('mqtt', 'QOS'))->toBe('1')
        ->and($device->serviceVariable('api', 'QOS'))->toBeNull();
});

it('falls back to the service id when installs__service was not expanded', function () {
    $device = Device::fromArray([
        'service_install' => [
            [
                'id' => 1,
                'installs__service' => ['__id' => 9],
                'device_service_environment_variable' => [
                    ['name' => 'PORT', 'value' => '8080'],
                ],
            ],
        ],
    ]);

    expect($device->serviceVariables())->toBe([9 => ['PORT' => '8080']]);
});

it('skips a service install that carries no variables', function () {
    $device = Device::fromArray([
        'service_install' => [
            ['id' => 1, 'installs__service' => [['id' => 7, 'service_name' => 'idle']]],
        ],
    ]);

    expect($device->serviceVariables())->toBe([]);
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
