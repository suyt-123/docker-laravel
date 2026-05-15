<?php

namespace App\Support;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;

class ActivityLogger
{
    /**
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $action,
        string $event,
        ?Model $subject = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?string $description = null,
        ?string $module = null,
    ): ?ActivityLog {
        if ($subject instanceof ActivityLog) {
            return null;
        }

        $request = Request::instance();

        return ActivityLog::create([
            'tenant_id' => $this->tenantId(),
            'actor_id' => Auth::id(),
            'action' => $action,
            'event' => $event,
            'subject_type' => $subject ? $subject::class : null,
            'subject_id' => $subject?->getKey(),
            'subject_label' => $subject ? $this->subjectLabel($subject) : null,
            'module' => $module ?? ($subject ? $this->moduleName($subject) : null),
            'description' => $description,
            'old_values' => $this->cleanValues($oldValues),
            'new_values' => $this->cleanValues($newValues),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'request_id' => $request?->headers->get('X-Request-Id') ?: (string) Str::uuid(),
        ]);
    }

    public function logCreated(Model $subject): ?ActivityLog
    {
        return $this->log(
            'create',
            'model.created',
            $subject,
            null,
            $subject->getAttributes(),
            "{$this->moduleName($subject)} 已建立",
        );
    }

    public function logUpdated(Model $subject): ?ActivityLog
    {
        $changes = Arr::except($subject->getChanges(), ['updated_at']);

        if ($changes === []) {
            return null;
        }

        $oldValues = collect($changes)
            ->mapWithKeys(fn ($value, string $key) => [$key => $subject->getOriginal($key)])
            ->all();

        return $this->log(
            'update',
            'model.updated',
            $subject,
            $oldValues,
            $changes,
            "{$this->moduleName($subject)} 已更新",
        );
    }

    public function logDeleted(Model $subject): ?ActivityLog
    {
        return $this->log(
            'delete',
            'model.deleted',
            $subject,
            $subject->getAttributes(),
            null,
            "{$this->moduleName($subject)} 已刪除",
        );
    }

    /**
     * @param  array<string, mixed>|null  $values
     * @return array<string, mixed>|null
     */
    private function cleanValues(?array $values): ?array
    {
        if ($values === null) {
            return null;
        }

        return Arr::except($values, [
            'password',
            'remember_token',
            'email_verified_at',
        ]);
    }

    private function tenantId(): ?int
    {
        return session('tenant_id') ? (int) session('tenant_id') : null;
    }

    private function subjectLabel(Model $subject): ?string
    {
        foreach (['name', 'title', 'project_no', 'quotation_no', 'email', 'work_item', 'file_path'] as $field) {
            if (filled($subject->getAttribute($field))) {
                return (string) $subject->getAttribute($field);
            }
        }

        return $subject->getKey() ? '#'.$subject->getKey() : null;
    }

    private function moduleName(Model $subject): string
    {
        return Str::of(class_basename($subject))
            ->snake()
            ->plural()
            ->toString();
    }
}
