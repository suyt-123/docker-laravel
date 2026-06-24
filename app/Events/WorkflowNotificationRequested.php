<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowNotificationRequested
{
    use Dispatchable, SerializesModels;

    /**
     * @param  array<int, string>  $capabilities
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $capabilities,
        public readonly ?string $actionUrl = null,
        public readonly ?int $excludeUserId = null,
        public readonly string $module = 'workflow',
        public readonly array $payload = [],
    ) {}
}
