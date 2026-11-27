<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;
use RuntimeException;

final class FakeNotificationFacade implements NotificationFacadeInterface
{
    /** @var list<NotifyData> */
    public array $sent = [];

    public bool $failing = false;

    public function notify(NotifyData $data): void
    {
        if ($this->failing) {
            throw new RuntimeException('Notification provider is unavailable.');
        }

        $this->sent[] = $data;
    }
}
