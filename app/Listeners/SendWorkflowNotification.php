<?php

namespace App\Listeners;

use App\Events\WorkflowNotificationRequested;
use App\Models\User;
use App\Notifications\WorkflowNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Notification;

class SendWorkflowNotification implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(WorkflowNotificationRequested $event): void
    {
        $users = User::query()
            ->whereHas('roles.capabilities', fn ($query) => $query->whereIn('code', $event->capabilities))
            ->when($event->excludeUserId, fn ($query) => $query->whereKeyNot($event->excludeUserId))
            ->get()
            ->unique('id')
            ->values();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send($users, new WorkflowNotification(
            title: $event->title,
            body: $event->body,
            actionUrl: $event->actionUrl,
            module: $event->module,
            payload: $event->payload,
        ));
    }
}
