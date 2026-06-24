<?php

namespace App\Presenters\Quotations;

use App\Models\Customer;
use App\Models\DocumentAttachment;
use App\Models\DocumentVersion;
use App\Models\Material;
use App\Models\Project;
use App\Models\Quotation;
use App\Models\QuotationTemplate;
use App\Presenters\Concerns\PresentsModelSummaries;
use App\Services\Documents\DocumentAttachmentService;

class QuotationPresenter
{
    use PresentsModelSummaries;

    public function __construct(private readonly DocumentAttachmentService $attachments) {}

    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return [
            'draft' => '草稿',
            'reviewing' => '審核中',
            'approved' => '已核准',
            'sent' => '已送出',
            'accepted' => '已接受',
            'rejected' => '已拒絕',
            'expired' => '已過期',
            'voided' => '已作廢',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function indexItem(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_no' => $quotation->quotation_no,
            'status' => $quotation->status,
            'customer' => $this->customerSummary($quotation->customer, ['id', 'name', 'phone', 'line_id', 'address']),
            'project' => $this->projectSummary($quotation->project, ['id', 'project_no', 'name']),
            'creator' => $this->userSummary($quotation->creator),
            'approver' => $this->userSummary($quotation->approver),
            'subtotal' => $quotation->subtotal,
            'tax' => $quotation->tax,
            'discount' => $quotation->discount,
            'total' => $quotation->total,
            'profit_rate' => $quotation->profit_rate,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'items_count' => $quotation->items_count,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function show(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_no' => $quotation->quotation_no,
            'status' => $quotation->status,
            'customer_confirmation_status' => $quotation->customer_confirmation_status,
            'customer_sent_at' => $quotation->customer_sent_at?->toDateTimeString(),
            'customer_confirmed_at' => $quotation->customer_confirmed_at?->toDateTimeString(),
            'customer_confirmed_by_name' => $quotation->customer_confirmed_by_name,
            'locked_at' => $quotation->locked_at?->toDateTimeString(),
            'voided_at' => $quotation->voided_at?->toDateTimeString(),
            'void_reason' => $quotation->void_reason,
            'reopened_from' => $this->quotationSummary($quotation->reopenedFrom),
            'superseded_by' => $this->quotationSummary($quotation->supersededBy),
            'customer' => $this->customerSummary($quotation->customer, ['id', 'name', 'phone', 'line_id', 'address']),
            'project' => $this->projectSummary($quotation->project, ['id', 'project_no', 'name', 'status', 'address']),
            'creator' => $this->userSummary($quotation->creator),
            'approver' => $this->userSummary($quotation->approver),
            'subtotal' => $quotation->subtotal,
            'tax' => $quotation->tax,
            'discount' => $quotation->discount,
            'total' => $quotation->total,
            'profit_rate' => $quotation->profit_rate,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'note' => $quotation->note,
            'items' => $quotation->items->map(fn ($item) => [
                'id' => $item->id,
                'material_id' => $item->material_id,
                'name' => $item->name,
                'spec' => $item->spec,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'cost_price' => $item->cost_price,
                'waste_rate' => $item->waste_rate,
                'subtotal' => $item->subtotal,
                'note' => $item->note,
                'material' => $this->materialSummary($item->material),
            ]),
            'document_versions' => $quotation->documentVersions
                ->sortByDesc('version_number')
                ->values()
                ->map(fn (DocumentVersion $version) => [
                    'id' => $version->id,
                    'category' => $version->category,
                    'version_number' => $version->version_number,
                    'status' => $version->status,
                    'file_name' => $version->file_name,
                    'size' => $version->size,
                    'generated_at' => $version->generated_at?->toDateTimeString(),
                    'generator' => $this->userSummary($version->generator),
                ]),
            'attachments' => $quotation->attachments
                ->sortByDesc('created_at')
                ->values()
                ->map(fn (DocumentAttachment $attachment) => [
                    'id' => $attachment->id,
                    'original_name' => $attachment->original_name,
                    'mime_type' => $attachment->mime_type,
                    'size' => $attachment->size,
                    'description' => $attachment->description,
                    'created_at' => $attachment->created_at?->toDateTimeString(),
                    'uploader' => $this->userSummary($attachment->uploader),
                    'url' => $this->attachments->url($attachment),
                ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function edit(Quotation $quotation): array
    {
        return [
            'id' => $quotation->id,
            'quotation_no' => $quotation->quotation_no,
            'customer_id' => $quotation->customer_id,
            'project_id' => $quotation->project_id,
            'quotation_template_id' => $quotation->quotation_template_id,
            'template_inputs' => $quotation->template_inputs ?? [],
            'status' => $quotation->status,
            'tax' => $quotation->tax,
            'discount' => $quotation->discount,
            'profit_rate' => $quotation->profit_rate,
            'valid_until' => $quotation->valid_until?->toDateString(),
            'note' => $quotation->note,
            'items' => $quotation->items->map(fn ($item) => [
                'material_id' => $item->material_id,
                'name' => $item->name,
                'spec' => $item->spec,
                'unit' => $item->unit,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'cost_price' => $item->cost_price,
                'waste_rate' => $item->waste_rate,
                'note' => $item->note,
            ]),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(bool $canViewCustomerPhone): array
    {
        $customerColumns = ['id', 'name'];
        if ($canViewCustomerPhone) {
            $customerColumns[] = 'phone';
        }

        return [
            'customers' => Customer::query()
                ->orderBy('name')
                ->get($customerColumns)
                ->map(fn (Customer $customer) => $this->customerSummary($customer)),
            'projects' => Project::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'customer_id'])
                ->map(fn (Project $project) => [
                    ...$this->projectSummary($project, ['id', 'project_no', 'name'], true),
                    'customer_id' => $project->customer_id,
                ]),
            'materials' => Material::query()
                ->with('category:id,name')
                ->orderBy('name')
                ->get(['id', 'material_category_id', 'name', 'spec', 'unit', 'cost_price', 'sale_price'])
                ->map(fn (Material $material) => [
                    ...$this->materialSummary($material),
                    'material_category_id' => $material->material_category_id,
                    'category' => $this->modelOnly($material->category, ['id', 'name']),
                ]),
            'quotationTemplates' => QuotationTemplate::query()
                ->where('status', 'active')
                ->with('items')
                ->orderBy('name')
                ->get()
                ->map(fn (QuotationTemplate $template) => [
                    'id' => $template->id,
                    'name' => $template->name,
                    'type' => $template->type,
                    'profit_rate' => $template->profit_rate,
                    'tax' => $template->tax,
                    'discount' => $template->discount,
                    'parameter_definitions' => $template->parameter_definitions ?? [],
                    'note' => $template->note,
                ]),
        ];
    }
}
