<?php

namespace App\Observers;

use App\Support\ActivityLogger;
use Illuminate\Database\Eloquent\Model;

class ActivityLogObserver
{
    public function created(Model $model): void
    {
        app(ActivityLogger::class)->logCreated($model);
    }

    public function updated(Model $model): void
    {
        app(ActivityLogger::class)->logUpdated($model);
    }

    public function deleted(Model $model): void
    {
        app(ActivityLogger::class)->logDeleted($model);
    }
}
