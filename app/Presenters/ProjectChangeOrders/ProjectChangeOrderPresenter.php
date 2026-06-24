<?php

namespace App\Presenters\ProjectChangeOrders;

use App\Auth\CapabilityAuthorizer;
use App\Auth\DataScope;
use App\Models\Project;
use App\Models\ProjectChangeOrder;
use App\Models\Quotation;
use App\Models\User;
use App\Presenters\Concerns\PresentsModelSummaries;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Model;

class ProjectChangeOrderPresenter
{
    use PresentsModelSummaries;

    public function __construct(
        private readonly CapabilityAuthorizer $authorizer,
        private readonly DataScope $dataScope,
    ) {}

    /**
     * @return array<string, string>
     */
    public function statuses(): array
    {
        return [
            'draft' => '草稿',
            'pending_approval' => '待主管核准',
            'approved' => '主管已核准',
            'customer_confirmed' => '客戶已確認',
            'converted' => '已轉追加款',
            'cancelled' => '已取消',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function defaultFormData(mixed $projectId): array
    {
        $projectId = is_scalar($projectId) ? (string) $projectId : '';

        return [
            'project_id' => $projectId,
            'status' => 'draft',
            'requested_date' => now()->toDateString(),
            'requires_formal_quotation' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function form(ProjectChangeOrder $order): array
    {
        return [
            'id' => $order->id,
            'project_id' => $order->project_id,
            'quotation_id' => $order->quotation_id,
            'title' => $order->title,
            'description' => $order->description,
            'amount' => $order->amount,
            'requires_formal_quotation' => $order->requires_formal_quotation,
            'requested_date' => $order->requested_date?->toDateString(),
            'approved_date' => $order->approved_date?->toDateString(),
            'due_date' => $order->due_date?->toDateString(),
            'status' => $order->status,
            'customer_note' => $order->customer_note,
            'internal_note' => $order->internal_note,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(ProjectChangeOrder $order, User $user): array
    {
        return [
            'id' => $order->id,
            'project' => $this->projectSummary($order->project, ['id', 'project_no', 'name'], true),
            'financial_record' => $this->financialRecordSummary($order->financialRecord),
            'quotation' => $this->quotationSummary($order->quotation, ['id', 'quotation_no', 'status', 'total']),
            'creator' => $this->userSummary($order->creator),
            'approver' => $this->userSummary($order->approver),
            'title' => $order->title,
            'description' => $order->description,
            'amount' => $order->amount,
            'requires_formal_quotation' => $order->requires_formal_quotation,
            'requested_date' => $order->requested_date?->toDateString(),
            'submitted_at' => $this->dateTime($order->submitted_at),
            'approved_date' => $order->approved_date?->toDateString(),
            'approved_at' => $this->dateTime($order->approved_at),
            'customer_confirmed_at' => $this->dateTime($order->customer_confirmed_at),
            'due_date' => $order->due_date?->toDateString(),
            'status' => $order->status,
            'customer_note' => $order->customer_note,
            'internal_note' => $order->internal_note,
            'converted_at' => $this->dateTime($order->converted_at),
            'can_submit_review' => $order->status === 'draft'
                && $this->authorizer->allows($user, 'projects.change_orders.submit_review.tenant'),
            'can_approve' => $order->status === 'pending_approval'
                && $this->authorizer->allows($user, 'projects.change_orders.approve.tenant'),
            'can_confirm_customer' => $order->status === 'approved'
                && $this->authorizer->allows($user, 'projects.change_orders.confirm_customer.tenant'),
            'can_cancel' => $order->status !== 'converted'
                && $this->authorizer->allows($user, 'projects.change_orders.cancel.tenant'),
            'can_create_quotation' => $order->requires_formal_quotation
                && $order->quotation_id === null
                && in_array($order->status, ['draft', 'pending_approval', 'approved'], true)
                && $this->authorizer->allows($user, 'projects.change_orders.create_quotation.tenant'),
            'can_convert' => $order->status === 'customer_confirmed'
                && $order->financial_record_id === null
                && $this->authorizer->allows($user, 'projects.change_orders.convert_financial_record.tenant'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formOptions(User $user): array
    {
        return [
            'projects' => $this->dataScope
                ->projects(Project::query(), $user)
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->get(['id', 'project_no', 'name', 'customer_id'])
                ->map(fn (Project $project) => [
                    ...$this->projectSummary($project, ['id', 'project_no', 'name'], true),
                    'customer_id' => $project->customer_id,
                ]),
            'quotations' => Quotation::query()
                ->with('customer:id,name')
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'quotation_no', 'customer_id', 'project_id', 'status', 'total'])
                ->map(fn (Quotation $quotation) => [
                    ...$this->quotationSummary($quotation, ['id', 'quotation_no', 'status', 'total']),
                    'customer_id' => $quotation->customer_id,
                    'project_id' => $quotation->project_id,
                    'customer' => $this->customerSummary($quotation->customer),
                ]),
        ];
    }

    private function dateTime(?DateTimeInterface $value): ?string
    {
        if (! $value) {
            return null;
        }

        if (method_exists($value, 'timezone')) {
            return $value->timezone(config('app.timezone'))->format('Y-m-d H:i');
        }

        return $value->format('Y-m-d H:i');
    }

    /**
     * @return array<string, mixed>|null
     */
    private function financialRecordSummary(?Model $record): ?array
    {
        return $this->modelOnly($record, ['id', 'title', 'status', 'amount', 'due_date']);
    }
}
