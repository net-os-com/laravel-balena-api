<?php

declare(strict_types=1);

use NetOs\Balena\Balena;
use NetOs\Balena\Data\Device;

function deviceDetailQuery(): array
{
    return app(Balena::class)->devices()->withDetails()->toQuery();
}

it('selects the host, network and note fields next to the status fields', function () {
    $selected = explode(',', (string) deviceDetailQuery()['$select']);

    expect($selected)->toContain(
        'overall_status',
        'os_version',
        'os_variant',
        'supervisor_version',
        'ip_address',
        'mac_address',
        'public_address',
        'note',
    );
});

it('expands the running, target and pinned releases and the tags', function () {
    $expand = (string) deviceDetailQuery()['$expand'];

    expect($expand)
        ->toContain('is_running__release($select=id,commit,raw_version,status,created_at)')
        ->toContain('should_be_running__release($select=')
        ->toContain('is_pinned_on__release($select=')
        ->toContain('device_tag($select=tag_key,value)');
});

it('keeps the fleet filter when asking for details in a fleet', function () {
    $query = app(Balena::class)->devices()->detailsInFleet('myorg/myfleet')->toQuery();

    expect((string) $query['$filter'])->toContain("slug eq 'myorg/myfleet'")
        ->and((string) $query['$select'])->toContain('mac_address');
});

it('reads expanded tags as a key-value map', function () {
    $device = Device::fromArray([
        'uuid' => 'abc',
        'device_tag' => [
            ['tag_key' => 'location', 'value' => 'amsterdam'],
            ['tag_key' => 'role', 'value' => 'kiosk'],
        ],
    ]);

    expect($device->tags())->toBe(['location' => 'amsterdam', 'role' => 'kiosk'])
        ->and($device->tag('role'))->toBe('kiosk')
        ->and($device->tag('absent'))->toBeNull();
});

it('reports no tags when device_tag was not expanded', function () {
    expect(Device::fromArray(['uuid' => 'abc'])->tags())->toBe([]);
});

it('reports a pending update only when both releases are known', function () {
    $behind = Device::fromArray([
        'is_running__release' => [['commit' => 'aaa']],
        'should_be_running__release' => [['commit' => 'bbb']],
    ]);

    $settled = Device::fromArray([
        'is_running__release' => [['commit' => 'aaa']],
        'should_be_running__release' => [['commit' => 'aaa']],
    ]);

    expect($behind->hasPendingUpdate())->toBeTrue()
        ->and($settled->hasPendingUpdate())->toBeFalse()
        ->and(Device::fromArray(['uuid' => 'abc'])->hasPendingUpdate())->toBeNull();
});
