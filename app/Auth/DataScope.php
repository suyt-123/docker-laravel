<?php

namespace App\Auth;

use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class DataScope
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer)
    {
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function projects(Builder $query, User $user): Builder
    {
        if ($this->authorizer->allows($user, 'projects.projects.view.tenant')) {
            return $query;
        }

        $worker = $user->worker;

        return $query->where(function (Builder $query) use ($user, $worker) {
            $query->where('manager_id', $user->id);

            if ($worker?->work_crew_id) {
                $query
                    ->orWhere('work_crew_id', $worker->work_crew_id)
                    ->orWhereHas('dispatches', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id));
            }

            if ($worker) {
                $query->orWhereHas('dispatches.workers', fn (Builder $query) => $query->where('workers.id', $worker->id));
            }
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function dispatches(Builder $query, User $user): Builder
    {
        if ($this->authorizer->allows($user, 'field.dispatches.view.tenant')) {
            return $query;
        }

        $worker = $user->worker;

        return $query->where(function (Builder $query) use ($user, $worker) {
            if ($this->authorizer->allows($user, 'field.dispatches.view.own')) {
                $query->whereHas('workers', fn (Builder $query) => $query->where('workers.user_id', $user->id));
            }

            if ($worker?->work_crew_id && $this->authorizer->allows($user, 'field.dispatches.view.assigned')) {
                $query
                    ->orWhere('work_crew_id', $worker->work_crew_id)
                    ->orWhereHas('project', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id));
            }

            if ($this->authorizer->allows($user, 'projects.projects.view.assigned')) {
                $query->orWhereHas('project', fn (Builder $query) => $query->where('manager_id', $user->id));
            }
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function workers(Builder $query, User $user): Builder
    {
        if ($this->authorizer->allows($user, 'field.workers.view.tenant')) {
            return $query;
        }

        $worker = $user->worker;

        return $query->where(function (Builder $query) use ($user, $worker) {
            if ($this->authorizer->allows($user, 'field.workers.view.own')) {
                $query->where('user_id', $user->id);
            }

            if ($worker?->work_crew_id && $this->authorizer->allows($user, 'field.workers.view.assigned')) {
                $query->orWhere('work_crew_id', $worker->work_crew_id);
            }
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function progressLogs(Builder $query, User $user): Builder
    {
        if ($this->authorizer->allows($user, 'field.progress_logs.view.tenant')) {
            return $query;
        }

        $worker = $user->worker;

        return $query->where(function (Builder $query) use ($user, $worker) {
            if ($this->authorizer->allows($user, 'field.progress_logs.view.own')) {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereHas('worker', fn (Builder $query) => $query->where('user_id', $user->id))
                    ->orWhereHas('dispatch.workers', fn (Builder $query) => $query->where('workers.user_id', $user->id));
            }

            if ($worker?->work_crew_id && $this->authorizer->allows($user, 'field.progress_logs.view.assigned')) {
                $query
                    ->orWhereHas('project', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id))
                    ->orWhereHas('dispatch', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id))
                    ->orWhereHas('worker', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id));
            }

            if ($this->authorizer->allows($user, 'projects.projects.view.assigned')) {
                $query->orWhereHas('project', fn (Builder $query) => $query->where('manager_id', $user->id));
            }
        });
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function attendanceRecords(Builder $query, User $user): Builder
    {
        if ($this->authorizer->allows($user, 'field.attendance.view.tenant')) {
            return $query;
        }

        $worker = $user->worker;

        return $query->where(function (Builder $query) use ($user, $worker) {
            if ($this->authorizer->allows($user, 'field.attendance.view.own')) {
                $query
                    ->where('user_id', $user->id)
                    ->orWhereHas('worker', fn (Builder $query) => $query->where('user_id', $user->id))
                    ->orWhereHas('dispatch.workers', fn (Builder $query) => $query->where('workers.user_id', $user->id));
            }

            if ($worker?->work_crew_id && $this->authorizer->allows($user, 'field.attendance.view.assigned')) {
                $query
                    ->orWhereHas('dispatch', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id))
                    ->orWhereHas('project', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id))
                    ->orWhereHas('worker', fn (Builder $query) => $query->where('work_crew_id', $worker->work_crew_id));
            }

            if ($this->authorizer->allows($user, 'projects.projects.view.assigned')) {
                $query->orWhereHas('project', fn (Builder $query) => $query->where('manager_id', $user->id));
            }
        });
    }
}
