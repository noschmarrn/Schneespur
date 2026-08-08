<?php

namespace App\Services\Notification;

/**
 * Lets a channel say which notification types it is responsible for.
 *
 * Deliberately a second, optional interface rather than a method on
 * NotificationChannelInterface: that one is a published contract, already
 * implemented by the bundled `example` module (and planned by `dokumente`),
 * so an added requirement there would be a fatal on its next update. A
 * channel without this interface keeps being called unconditionally,
 * exactly as before.
 */
interface SupportsNotificationTypes
{
    public function supports(string $type): bool;
}
