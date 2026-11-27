<?php

declare(strict_types=1);

namespace Tests\Feature\Invoices;

use Modules\Notifications\Api\Data\NotifyData;
use Modules\Notifications\Api\NotificationFacadeInterface;

final class FakeNotificationFacade implements NotificationFacadeInterface
{
    /** @var list<NotifyData> */
    public array $sent = [];

    public function notify(NotifyData $data): void
    {
        $this->sent[] = $data;
    }
}
