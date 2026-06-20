<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));

        $users = User::query()
            ->with('roles:id,name,code')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhereHas('roles', fn ($query) => $query->where('name', 'ilike', "%{$search}%"));
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (User $user) => $this->userPayload($user));

        return Inertia::render('Users/Index', [
            'users' => $users,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Users/Create', [
            'roles' => $this->roles(),
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $roleIds = $data['roles'] ?? [];

        $this->ensureCanSyncRoles($request, $roleIds);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'email_verified_at' => ($data['email_verified'] ?? false) ? now() : null,
        ]);

        $user->roles()->sync($roleIds);

        return redirect()
            ->route('users.show', $user)
            ->with('success', '使用者已建立。');
    }

    public function show(User $user): Response
    {
        $user->load(['roles.capabilities']);

        return Inertia::render('Users/Show', [
            'user' => [
                ...$this->userPayload($user),
                'capabilities' => $user->roles
                    ->flatMap(fn (Role $role) => $role->capabilities)
                    ->unique('id')
                    ->sortBy('group')
                    ->values()
                    ->map(fn ($capability) => [
                        'id' => $capability->id,
                        'name' => $capability->name,
                        'code' => $capability->code,
                        'group' => $capability->group,
                    ]),
            ],
        ]);
    }

    public function edit(User $user): Response
    {
        $user->load('roles:id');

        return Inertia::render('Users/Edit', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'email_verified' => filled($user->email_verified_at),
                'roles' => $user->roles->pluck('id')->all(),
            ],
            'roles' => $this->roles(),
        ]);
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $data = $request->validated();
        $roleIds = $data['roles'] ?? [];

        $this->ensureCanSyncRoles($request, $roleIds, $user);

        $payload = [
            'name' => $data['name'],
            'email' => $data['email'],
            'email_verified_at' => ($data['email_verified'] ?? false) ? ($user->email_verified_at ?? now()) : null,
        ];

        if (filled($data['password'] ?? null)) {
            $payload['password'] = Hash::make($data['password']);
        }

        $user->update($payload);
        $user->roles()->sync($roleIds);

        return redirect()
            ->route('users.show', $user)
            ->with('success', '使用者已更新。');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()?->is($user)) {
            return redirect()
                ->route('users.index')
                ->with('success', '不能刪除目前登入的使用者。');
        }

        $user->delete();

        return redirect()
            ->route('users.index')
            ->with('success', '使用者已刪除。');
    }

    private function roles()
    {
        return Role::query()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'description']);
    }

    /**
     * @param  array<int, mixed>  $roleIds
     */
    private function ensureCanSyncRoles(Request $request, array $roleIds, ?User $user = null): void
    {
        $requestedRoleIds = collect($roleIds)
            ->map(fn ($roleId) => (int) $roleId)
            ->sort()
            ->values();

        $currentRoleIds = $user
            ? $user->roles()->pluck('roles.id')->map(fn ($roleId) => (int) $roleId)->sort()->values()
            : collect();

        if ($requestedRoleIds->all() === $currentRoleIds->all()) {
            return;
        }

        abort_unless(
            $request->user() && $this->authorizer->allows($request->user(), 'security.roles.assign_capabilities.tenant'),
            403,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'email_verified_at' => $user->email_verified_at?->toDateTimeString(),
            'email_verified' => filled($user->email_verified_at),
            'created_at' => $user->created_at?->toDateTimeString(),
            'roles' => $user->roles->map(fn (Role $role) => [
                'id' => $role->id,
                'name' => $role->name,
                'code' => $role->code,
            ]),
        ];
    }
}
