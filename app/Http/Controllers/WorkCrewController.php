<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkCrewRequest;
use App\Http\Requests\UpdateWorkCrewRequest;
use App\Models\WorkCrew;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkCrewController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $workCrews = WorkCrew::query()
            ->withCount(['workers', 'dispatches', 'projects'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('leader_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%");
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (WorkCrew $workCrew) => [
                'id' => $workCrew->id,
                'name' => $workCrew->name,
                'leader_name' => $workCrew->leader_name,
                'phone' => $workCrew->phone,
                'specialties' => $workCrew->specialties,
                'daily_rate' => $workCrew->daily_rate,
                'workers_count' => $workCrew->workers_count,
                'dispatches_count' => $workCrew->dispatches_count,
                'projects_count' => $workCrew->projects_count,
            ]);

        return Inertia::render('WorkCrews/Index', [
            'workCrews' => $workCrews,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('WorkCrews/Create');
    }

    public function store(StoreWorkCrewRequest $request): RedirectResponse
    {
        $workCrew = WorkCrew::create($this->crewData($request->validated()));

        return redirect()
            ->route('work-crews.show', $workCrew)
            ->with('success', '工班已建立。');
    }

    public function show(WorkCrew $workCrew): Response
    {
        $workCrew->load([
            'workers' => fn ($query) => $query->orderByDesc('is_active')->orderBy('name'),
            'dispatches' => fn ($query) => $query->with('project:id,project_no,name')->latest()->limit(8),
            'projects' => fn ($query) => $query->latest()->limit(8),
        ]);

        return Inertia::render('WorkCrews/Show', [
            'workCrew' => [
                'id' => $workCrew->id,
                'name' => $workCrew->name,
                'leader_name' => $workCrew->leader_name,
                'phone' => $workCrew->phone,
                'specialties' => $workCrew->specialties,
                'daily_rate' => $workCrew->daily_rate,
                'note' => $workCrew->note,
                'workers' => $workCrew->workers,
                'dispatches' => $workCrew->dispatches,
                'projects' => $workCrew->projects,
            ],
        ]);
    }

    public function edit(WorkCrew $workCrew): Response
    {
        return Inertia::render('WorkCrews/Edit', [
            'workCrew' => [
                'id' => $workCrew->id,
                'name' => $workCrew->name,
                'leader_name' => $workCrew->leader_name,
                'phone' => $workCrew->phone,
                'specialties_text' => implode("\n", $workCrew->specialties ?? []),
                'daily_rate' => $workCrew->daily_rate,
                'note' => $workCrew->note,
            ],
        ]);
    }

    public function update(UpdateWorkCrewRequest $request, WorkCrew $workCrew): RedirectResponse
    {
        $workCrew->update($this->crewData($request->validated()));

        return redirect()
            ->route('work-crews.show', $workCrew)
            ->with('success', '工班已更新。');
    }

    public function destroy(WorkCrew $workCrew): RedirectResponse
    {
        $workCrew->delete();

        return redirect()
            ->route('work-crews.index')
            ->with('success', '工班已刪除。');
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function crewData(array $data): array
    {
        $specialties = collect(preg_split('/\r\n|\r|\n|,/', (string) ($data['specialties_text'] ?? '')))
            ->map(fn ($item) => trim($item))
            ->filter()
            ->values()
            ->all();

        unset($data['specialties_text']);

        return [
            ...$data,
            'specialties' => $specialties,
            'daily_rate' => $data['daily_rate'] ?? null,
        ];
    }
}
