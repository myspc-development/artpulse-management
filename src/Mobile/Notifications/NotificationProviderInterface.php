<?php

namespace ArtPulse\Mobile\Notifications;

/**
 * Defines the contract used by the notification pipeline to deliver messages to devices.
 */
interface NotificationProviderInterface
{
    /**
     * Deliver a notification payload to the given device.
     *
     * @param array<string, mixed> $payload
     */
    public function send(int $user_id, string $device_id, string $topic, array $payload): void;
}
