<?php

namespace App\Http\Controllers;

use App\Auth\DataScope;
use App\Http\Requests\StoreWorkerRequest;
use App\Http\Requests\UpdateWorkerRequest;
use App\Models\User;
use App\Models\WorkCrew;
use App\Models\Worker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkerController extends Controller
{
    public function __construct(private readonly DataScope $dataScope) {}

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $active = trim((string) $request->query('active', ''));

        $workers = $this->dataScope->workers(Worker::query(), $request->user())
            ->with(['workCrew:id,name', 'user:id,name,email'])
            ->withCount('dispatches')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('role', 'ilike', "%{$search}%")
                    ->orWhereHas('workCrew', fn ($query) => $query->where('name', 'ilike', "%{$search}%"))
                    ->orWhereHas('user', fn ($query) => $query
                        ->where('name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"));
            }))
            ->when($active !== '', fn ($query) => $query->where('is_active', $active === '1'))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Worker $worker) => [
                'id' => $worker->id,
                'user' => $worker->user,
                'work_crew' => $worker->workCrew,
                'name' => $worker->name,
                'phone' => $worker->phone,
                'role' => $worker->role,
                'daily_rate' => $worker->daily_rate,
                'certifications' => $worker->certifications,
                'insurance_expires_at' => $worker->insurance_expires_at?->toDateString(),
                'is_active' => $worker->is_active,
                'dispatches_count' => $worker->dispatches_count,
            ]);

        return Inertia::render('Workers/Index', [
            'workers' => $workers,
            'filters' => [
                'search' => $search,
                'active' => $active,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Workers/Create', [
            'workCrews' => $this->workCrews(),
            'users' => $this->userOptions(),
        ]);
    }

    public function store(StoreWorkerRequest $request): RedirectResponse
    {
        $worker = Worker::create($this->workerData($request->validated()));

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', '師傅已建立。');
    }

    public function show(Request $request, Worker $worker): Response
    {
        $this->ensureVisible($request, $worker);

        $worker->load([
            'workCrew:id,name,leader_name,phone',
            'user:id,name,email',
            'dispatches' => fn ($query) => $query
                ->with('project:id,project_no,name')
                ->orderByDesc('dispatches.created_at')
                ->limit(12),
        ]);

        return Inertia::render('Workers/Show', [
            'worker' => [
                'id' => $worker->id,
                'user' => $worker->user,
                'work_crew' => $worker->workCrew,
                'name' => $worker->name,
                'phone' => $worker->phone,
                'role' => $worker->role,
                'daily_rate' => $worker->daily_rate,
                'certifications' => $worker->certifications,
                'insurance_expires_at' => $worker->insurance_expires_at?->toDateString(),
                'is_active' => $worker->is_active,
                'note' => $worker->note,
                'dispatches' => $worker->dispatches,
            ],
        ]);
    }

    public function edit(Request $request, Worker $worker): Response
    {
        $this->ensureVisible($request, $worker);

        return Inertia::render('Workers/Edit', [
            'worker' => [
                'id' => $worker->id,
                'user_id' => $worker->user_id,
                'work_crew_id' => $worker->work_crew_id,
                'name' => $worker->name,
                'phone' => $worker->phone,
                'role' => $worker->role,
                'daily_rate' => $worker->daily_rate,
                'certifications_text' => implode("\n", $worker->certifications ?? []),
                'insurance_expires_at' => $worker->insurance_expires_at?->toDateString(),
                'is_active' => $worker->is_active,
                'note' => $worker->note,
            ],
            'workCrews' => $this->workCrews(),
            'users' => $this->userOptions($worker),
        ]);
    }

    public function update(UpdateWorkerRequest $request, Worker $worker): RedirectResponse
    {
        $this->ensureVisible($request, $worker);

        $worker->update($this->workerData($request->validated()));

        return redirect()
            ->route('workers.show', $worker)
            ->with('success', '師傅已更新。');
    }

    public function destroy(Request $request, Worker $worker): RedirectResponse
    {
        $this->ensureVisible($request, $worker);

        $worker->delete();

        return redirect()
            ->route('workers.index')
            ->with('success', '師傅已刪除。');
    }

    private function workCrews()
    {
        return WorkCrew::query()->orderBy('name')->get(['id', 'name', 'leader_name']);
    }

    private function userOptions(?Worker $worker = null)
    {
        return User::query()
            ->with('roles:id,name,code')
            ->where(fn ($query) => $query
                ->whereDoesntHave('worker')
                ->when($worker?->user_id, fn ($query) => $query->orWhere('id', $worker->user_id)))
            ->orderBy('name')
            ->get(['id', 'name', 'email'])
            ->map(fn (User $user) => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'roles' => $user->roles->map(fn ($role) => [
                    'id' => $role->id,
                    'name' => $role->name,
                    'code' => $role->code,
                ]),
            ]);
    }

    private function ensureVisible(Request $request, Worker $worker): void
    {
        $visible = $this->dataScope
            ->workers(Worker::query(), $request->user())
            ->whereKey($worker->id)
            ->exists();

        abort_unless($visible, 403);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function workerData(array $data): array
    {
        $certifications = collect(preg_split('/\r\n|\r|\n|,/', (string) ($data['certifications_text'] ?? '')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        unset($data['certifications_text']);

        return [
            ...$data,
            'user_id' => $data['user_id'] ?? null,
            'certifications' => $certifications,
            'is_active' => (bool) ($data['is_active'] ?? false),
            'daily_rate' => $data['daily_rate'] ?? null,
        ];
    }
}
