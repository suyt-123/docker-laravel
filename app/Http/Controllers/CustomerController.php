<?php

namespace App\Http\Controllers;

use App\Auth\CapabilityAuthorizer;
use App\Http\Requests\StoreCustomerRequest;
use App\Http\Requests\UpdateCustomerRequest;
use App\Models\Customer;
use App\Models\Project;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Inertia;
use Inertia\Response;

class CustomerController extends Controller
{
    public function __construct(private readonly CapabilityAuthorizer $authorizer)
    {
    }

    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $canViewContact = $this->canViewContact($request);

        $customers = Customer::query()
            ->withCount(['projects', 'quotations'])
            ->with($canViewContact ? ['contacts' => fn ($query) => $query
                ->where('is_primary', true)
                ->latest('id')
                ->limit(1)] : [])
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search, $canViewContact) {
                $query->where('name', 'ilike', "%{$search}%");

                if ($canViewContact) {
                    $query
                        ->orWhere('phone', 'ilike', "%{$search}%")
                        ->orWhere('line_id', 'ilike', "%{$search}%")
                        ->orWhere('tax_id', 'ilike', "%{$search}%");
                }
            }))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Customer $customer) => [
                'id' => $customer->id,
                'name' => $customer->name,
                ...$this->contactPayload($customer, $canViewContact),
                'projects_count' => $customer->projects_count,
                'quotations_count' => $customer->quotations_count,
                'primary_contact' => $canViewContact ? $customer->contacts->first() : null,
                'created_at' => $customer->created_at?->toDateString(),
            ]);

        return Inertia::render('Customers/Index', [
            'customers' => $customers,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Customers/Create');
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $customer = Customer::create($request->safe()->except('primary_contact'));
        $this->syncPrimaryContact($customer, $request->validated('primary_contact', []));

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', '客戶已建立。');
    }

    public function show(Request $request, Customer $customer): Response
    {
        $canViewProjectFinancials = $this->authorizer->allows($request->user(), 'projects.projects.view_financials.tenant');
        $canViewQuotations = $this->authorizer->allows($request->user(), 'sales.quotations.view.tenant');
        $canViewContact = $this->canViewContact($request);

        $customer->load([
            ...($canViewContact ? ['contacts'] : []),
            'projects' => fn ($query) => $query->latest()->limit(8),
            ...($canViewQuotations ? [
                'quotations' => fn ($query) => $query->latest()->limit(8),
            ] : []),
        ]);

        return Inertia::render('Customers/Show', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                ...$this->contactPayload($customer, $canViewContact),
                'contacts' => $canViewContact ? $customer->contacts : [],
                'projects' => $customer->projects->map(fn (Project $project) => [
                    'id' => $project->id,
                    'project_no' => $project->project_no,
                    'name' => $project->name,
                    'status' => $project->status,
                    ...($canViewProjectFinancials ? [
                        'contract_amount' => $project->contract_amount,
                    ] : []),
                ]),
                'quotations' => $canViewQuotations ? $customer->quotations : [],
                'created_at' => $customer->created_at?->toDateString(),
            ],
        ]);
    }

    public function edit(Customer $customer): Response
    {
        $customer->load(['contacts' => fn ($query) => $query->orderByDesc('is_primary')->latest('id')]);

        return Inertia::render('Customers/Edit', [
            'customer' => [
                'id' => $customer->id,
                'name' => $customer->name,
                'phone' => $customer->phone,
                'line_id' => $customer->line_id,
                'tax_id' => $customer->tax_id,
                'source' => $customer->source,
                'address' => $customer->address,
                'note' => $customer->note,
                'primary_contact' => $customer->contacts->firstWhere('is_primary', true),
            ],
        ]);
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $customer->update($request->safe()->except('primary_contact'));
        $this->syncPrimaryContact($customer, $request->validated('primary_contact', []));

        return redirect()
            ->route('customers.show', $customer)
            ->with('success', '客戶已更新。');
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $customer->delete();

        return redirect()
            ->route('customers.index')
            ->with('success', '客戶已刪除。');
    }

    /**
     * @param  array<string, mixed>  $contact
     */
    private function syncPrimaryContact(Customer $customer, array $contact): void
    {
        $contact = Arr::only($contact, ['name', 'title', 'phone', 'email', 'line_id']);
        $hasContact = collect($contact)->filter(fn ($value) => filled($value))->isNotEmpty();
        $primaryContact = $customer->contacts()->where('is_primary', true)->first();

        if (! $hasContact) {
            $primaryContact?->delete();

            return;
        }

        $customer->contacts()->updateOrCreate(
            ['id' => $primaryContact?->id],
            array_merge($contact, ['is_primary' => true]),
        );
    }

    private function canViewContact(Request $request): bool
    {
        return $this->authorizer->allows($request->user(), 'crm.customers.view_contact.tenant');
    }

    /**
     * @return array<string, mixed>
     */
    private function contactPayload(Customer $customer, bool $canViewContact): array
    {
        if (! $canViewContact) {
            return [];
        }

        return [
            'phone' => $customer->phone,
            'line_id' => $customer->line_id,
            'tax_id' => $customer->tax_id,
            'source' => $customer->source,
            'address' => $customer->address,
            'note' => $customer->note,
        ];
    }
}
