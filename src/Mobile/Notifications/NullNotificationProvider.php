<?php

namespace ArtPulse\Mobile\Notifications;

class NullNotificationProvider implements NotificationProviderInterface
{
    /**
     * @param array<string, mixed> $payload
     */
    public function send(int $user_id, string $device_id, string $topic, array $payload): void
    {
        // Intentionally left blank. This provider disables outbound notifications.
    }
}
