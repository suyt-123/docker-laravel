<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Capability;
use App\Models\Role;
use App\Support\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoleController extends Controller
{
    private const CRUD_ACTIONS = [
        'view' => '查看',
        'create' => '新增',
        'update' => '編輯',
        'delete' => '刪除',
    ];

    private const SCOPE_LABELS = [
        'tenant' => '全部',
        'assigned' => '指派',
        'own' => '本人',
        'global' => '全域',
    ];

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $roles = Role::query()
            ->withCount(['capabilities', 'users'])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('code', 'ilike', "%{$search}%");
            }))
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Role $role) => $this->rolePayload($role));

        return Inertia::render('Roles/Index', [
            'roles' => $roles,
            'filters' => ['search' => $search],
        ]);
    }

    public function matrix(): Response
    {
        $roles = Role::query()
            ->with(['capabilities' => fn ($query) => $query->orderBy('group')->orderBy('code')])
            ->orderByDesc('is_system')
            ->orderBy('name')
            ->get();

        $capabilities = Capability::query()
            ->orderBy('group')
            ->orderBy('code')
            ->get();

        $modules = $capabilities
            ->whereIn('action', array_keys(self::CRUD_ACTIONS))
            ->groupBy(fn (Capability $capability) => "{$capability->domain}.{$capability->resource}")
            ->map(fn ($items, string $key) => [
                'key' => $key,
                'label' => $items->first()->group ?? $key,
                'domain' => $items->first()->domain,
                'resource' => $items->first()->resource,
            ])
            ->sortBy('label')
            ->values();

        $rolePayloads = $roles->map(fn (Role $role) => [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'is_system' => $role->is_system,
            'is_protected' => $role->is_protected,
        ]);

        $matrix = $modules->map(function (array $module) use ($roles, $capabilities) {
            return [
                ...$module,
                'roles' => $roles->mapWithKeys(function (Role $role) use ($module, $capabilities) {
                    $roleCodes = $role->capabilities->pluck('code');

                    return [
                        $role->id => collect(self::CRUD_ACTIONS)->mapWithKeys(function (string $label, string $action) use ($module, $capabilities, $roleCodes) {
                            $matching = $capabilities
                                ->where('domain', $module['domain'])
                                ->where('resource', $module['resource'])
                                ->where('action', $action);
                            $granted = $matching->filter(fn (Capability $capability) => $roleCodes->contains($capability->code));

                            return [
                                $action => [
                                    'label' => $label,
                                    'granted' => $granted->isNotEmpty(),
                                    'scopes' => $granted
                                        ->pluck('scope')
                                        ->unique()
                                        ->map(fn (string $scope) => [
                                            'code' => $scope,
                                            'label' => self::SCOPE_LABELS[$scope] ?? $scope,
                                        ])
                                        ->values(),
                                ],
                            ];
                        }),
                    ];
                }),
            ];
        });

        $specialCapabilities = $capabilities
            ->whereNotIn('action', array_keys(self::CRUD_ACTIONS))
            ->map(fn (Capability $capability) => [
                ...$this->capabilityPayload($capability),
                'roles' => $roles->mapWithKeys(fn (Role $role) => [
                    $role->id => $role->capabilities->contains('code', $capability->code),
                ]),
            ])
            ->values();

        return Inertia::render('Roles/Matrix', [
            'roles' => $rolePayloads,
            'actions' => self::CRUD_ACTIONS,
            'matrix' => $matrix,
            'specialCapabilities' => $specialCapabilities,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Roles/Create', [
            'capabilities' => $this->capabilities(),
        ]);
    }

    public function store(StoreRoleRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $role = Role::create([
            'name' => $data['name'],
            'code' => $data['code'],
            'description' => $data['description'] ?? null,
            'is_system' => false,
            'is_protected' => false,
        ]);

        $role->capabilities()->sync($data['capabilities'] ?? []);
        $role->load('capabilities');

        app(ActivityLogger::class)->log(
            'assign_capabilities',
            'role.capabilities_assigned',
            $role,
            null,
            [
                'capabilities' => $role->capabilities->pluck('code')->values()->all(),
            ],
            '角色 capability 已指派',
            'roles',
        );

        return redirect()
            ->route('roles.show', $role)
            ->with('success', '角色已建立。');
    }

    public function show(Role $role): Response
    {
        $role->load(['capabilities' => fn ($query) => $query->orderBy('group')->orderBy('code')]);

        return Inertia::render('Roles/Show', [
            'role' => [
                ...$this->rolePayload($role),
                'description' => $role->description,
                'capabilities' => $role->capabilities->map(fn (Capability $capability) => $this->capabilityPayload($capability)),
            ],
        ]);
    }

    public function edit(Role $role): Response
    {
        $role->load('capabilities:id');

        return Inertia::render('Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
                'description' => $role->description,
                'is_system' => $role->is_system,
                'is_protected' => $role->is_protected,
                'capabilities' => $role->capabilities->pluck('id')->all(),
            ],
            'capabilities' => $this->capabilities(),
        ]);
    }

    public function update(UpdateRoleRequest $request, Role $role): RedirectResponse
    {
        $data = $request->validated();
        $before = $role->capabilities()->pluck('code')->sort()->values();

        $role->update([
            'name' => $data['name'],
            'code' => $role->code,
            'description' => $data['description'] ?? null,
        ]);

        $role->capabilities()->sync($data['capabilities'] ?? []);
        $role->load('capabilities');
        $after = $role->capabilities->pluck('code')->sort()->values();

        app(ActivityLogger::class)->log(
            'assign_capabilities',
            'role.capabilities_synced',
            $role,
            [
                'capabilities' => $before->all(),
            ],
            [
                'capabilities' => $after->all(),
                'added' => $after->diff($before)->values()->all(),
                'removed' => $before->diff($after)->values()->all(),
            ],
            '角色 capability 已同步',
            'roles',
        );

        return redirect()
            ->route('roles.show', $role)
            ->with('success', '角色已更新。');
    }

    public function destroy(Role $role): RedirectResponse
    {
        if ($role->is_protected) {
            return redirect()
                ->route('roles.index')
                ->with('success', '系統保護角色不可刪除。');
        }

        $role->delete();

        return redirect()
            ->route('roles.index')
            ->with('success', '角色已刪除。');
    }

    private function capabilities()
    {
        return Capability::query()
            ->orderBy('group')
            ->orderBy('code')
            ->get()
            ->map(fn (Capability $capability) => $this->capabilityPayload($capability));
    }

    /**
     * @return array<string, mixed>
     */
    private function rolePayload(Role $role): array
    {
        return [
            'id' => $role->id,
            'name' => $role->name,
            'code' => $role->code,
            'description' => $role->description,
            'is_system' => $role->is_system,
            'is_protected' => $role->is_protected,
            'capabilities_count' => $role->capabilities_count ?? $role->capabilities->count(),
            'users_count' => $role->users_count ?? $role->users->count(),
            'created_at' => $role->created_at?->toDateTimeString(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function capabilityPayload(Capability $capability): array
    {
        return [
            'id' => $capability->id,
            'name' => $capability->name,
            'code' => $capability->code,
            'group' => $capability->group,
            'domain' => $capability->domain,
            'resource' => $capability->resource,
            'action' => $capability->action,
            'scope' => $capability->scope,
        ];
    }
}
