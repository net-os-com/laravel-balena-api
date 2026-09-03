<?php

declare(strict_types=1);

namespace NetOs\Balena\Enums;

/**
 * The six variable resources balena exposes.
 *
 * They are structurally identical — a name and a value hanging off an owning
 * resource — and differ only in the endpoint and the field naming the owner.
 * Modelling that difference as data means one VariableResource covers all six.
 *
 * NOTE: these resource names come from balena's Pine model rather than from a
 * documentation page showing them, so they are worth confirming against a live
 * token before relying on the less common ones.
 */
enum VariableKind: string
{
    case DeviceEnvironment = 'device_environment_variable';
    case DeviceConfig = 'device_config_variable';
    case DeviceService = 'device_service_environment_variable';
    case FleetEnvironment = 'application_environment_variable';
    case FleetConfig = 'application_config_variable';
    case FleetService = 'service_environment_variable';

    public function resource(): string
    {
        return $this->value;
    }

    /**
     * The field linking a variable back to whatever owns it.
     *
     * Device service variables hang off a service install rather than a
     * device, and fleet service variables off a service.
     */
    public function ownerField(): string
    {
        return match ($this) {
            self::DeviceEnvironment, self::DeviceConfig => 'device',
            self::DeviceService => 'service_install',
            self::FleetEnvironment, self::FleetConfig => 'application',
            self::FleetService => 'service',
        };
    }
}
