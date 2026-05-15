<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSupplierRequest;
use App\Http\Requests\UpdateSupplierRequest;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->query('search', ''));
        $active = trim((string) $request->query('active', ''));

        $suppliers = Supplier::query()
            ->withCount('purchaseOrders')
            ->when($search !== '', fn ($query) => $query->where(function ($query) use ($search) {
                $query
                    ->where('name', 'ilike', "%{$search}%")
                    ->orWhere('contact_name', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhere('tax_id', 'ilike', "%{$search}%");
            }))
            ->when($active !== '', fn ($query) => $query->where('is_active', $active === '1'))
            ->latest()
            ->paginate(12)
            ->withQueryString()
            ->through(fn (Supplier $supplier) => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'tax_id' => $supplier->tax_id,
                'payment_terms' => $supplier->payment_terms,
                'is_active' => $supplier->is_active,
                'purchase_orders_count' => $supplier->purchase_orders_count,
            ]);

        return Inertia::render('Suppliers/Index', [
            'suppliers' => $suppliers,
            'filters' => [
                'search' => $search,
                'active' => $active,
            ],
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Suppliers/Create');
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $supplier = Supplier::create($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', '供應商已建立。');
    }

    public function show(Supplier $supplier): Response
    {
        $supplier->load([
            'purchaseOrders' => fn ($query) => $query->latest()->limit(8),
        ]);

        return Inertia::render('Suppliers/Show', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'tax_id' => $supplier->tax_id,
                'address' => $supplier->address,
                'payment_terms' => $supplier->payment_terms,
                'is_active' => $supplier->is_active,
                'note' => $supplier->note,
                'purchase_orders' => $supplier->purchaseOrders,
            ],
        ]);
    }

    public function edit(Supplier $supplier): Response
    {
        return Inertia::render('Suppliers/Edit', [
            'supplier' => [
                'id' => $supplier->id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'phone' => $supplier->phone,
                'email' => $supplier->email,
                'tax_id' => $supplier->tax_id,
                'address' => $supplier->address,
                'payment_terms' => $supplier->payment_terms,
                'is_active' => $supplier->is_active,
                'note' => $supplier->note,
            ],
        ]);
    }

    public function update(UpdateSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $supplier->update($request->validated());

        return redirect()
            ->route('suppliers.show', $supplier)
            ->with('success', '供應商已更新。');
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        abort_if($supplier->purchaseOrders()->exists(), 422, '已有採購單的供應商不可刪除。');

        $supplier->delete();

        return redirect()
            ->route('suppliers.index')
            ->with('success', '供應商已刪除。');
    }
}
