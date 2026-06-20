import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useMemo, useState } from 'react';

export default function Show({ project, statuses }) {
    const { flash = {} } = usePage().props;
    const { can, canAny, canViewProjectFinancials } = useAuthorization();
    const canUpdate = can(CAPABILITIES.projects.update);
    const canDelete = can(CAPABILITIES.projects.delete);
    const showFinancials = canViewProjectFinancials();
    const canViewQuotations = can(CAPABILITIES.quotations.view);
    const canViewChangeOrders = can(CAPABILITIES.projectChangeOrders.view);
    const canCreateChangeOrder = can(CAPABILITIES.projectChangeOrders.create);
    const canViewFinancialRecords = can(CAPABILITIES.financialRecords.view);
    const canExportFinancialRecords = can(
        CAPABILITIES.financialRecords.exportPdf,
    );
    const canViewInventoryTransactions = can(
        CAPABILITIES.inventoryTransactions.view,
    );
    const canViewProgressLogs = canAny([
        CAPABILITIES.progressLogs.view,
        CAPABILITIES.progressLogs.viewAssigned,
        CAPABILITIES.progressLogs.viewOwn,
    ]);

    const destroyProject = () => {
        if (!window.confirm(`確定要刪除「${project.name}」嗎？`)) {
            return;
        }

        router.delete(route('projects.destroy', project.id));
    };

    const canPreviewInvoice =
        canExportFinancialRecords && project.can_export_invoice_pdf;

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {project.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {project.project_no}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('projects.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('projects.edit', project.id)}>
                                <PrimaryButton>編輯案件</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={project.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid gap-5 lg:grid-cols-3">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                            <h3 className="text-base font-semibold text-gray-950">
                                案件資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="客戶"
                                    value={project.customer?.name}
                                />
                                <Info
                                    label="狀態"
                                    value={
                                        statuses[project.status] ??
                                        project.status
                                    }
                                />
                                <Info label="工程類型" value={project.type} />
                                <Info
                                    label="負責人"
                                    value={project.manager?.name}
                                />
                                <Info
                                    label="工班"
                                    value={project.work_crew?.name}
                                />
                                <Info
                                    label="施工日期"
                                    value={`${project.start_date || '未排程'}${
                                        project.end_date
                                            ? ` 至 ${project.end_date}`
                                            : ''
                                    }`}
                                />
                                <Info
                                    label="工程地址"
                                    value={project.address}
                                    wide
                                />
                            </dl>
                        </section>

                        {showFinancials && (
                            <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                                <h3 className="text-base font-semibold text-gray-950">
                                    金額概況
                                </h3>
                                <dl className="mt-5 space-y-4">
                                    <Info
                                        label="合約金額"
                                        value={money(project.contract_amount)}
                                    />
                                    <Info
                                        label="預估成本"
                                        value={money(project.estimated_cost)}
                                    />
                                    <Info
                                        label="實際成本"
                                        value={money(project.actual_cost)}
                                    />
                                    <Info
                                        label="目前毛利"
                                        value={money(project.gross_profit)}
                                    />
                                </dl>
                            </section>
                        )}
                    </div>

                    <div className="grid gap-5 lg:grid-cols-2">
                        {canViewQuotations && (
                            <RelatedList
                                title="近期報價"
                                empty="尚無報價"
                                items={project.quotations}
                                render={(quotation) => (
                                    <>
                                        <div>
                                            <div className="font-medium text-gray-950">
                                                {quotation.quotation_no}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {quotation.status}
                                            </div>
                                        </div>
                                        <div className="text-sm text-gray-600">
                                            {money(quotation.total)}
                                        </div>
                                    </>
                                )}
                            />
                        )}

                        {canViewChangeOrders && (
                            <ChangeOrdersList
                                project={project}
                                orders={project.change_orders}
                                statuses={project.change_order_statuses}
                                canCreate={
                                    canCreateChangeOrder &&
                                    project.can_create_change_order
                                }
                            />
                        )}

                        <RelatedList
                            title="近期派工"
                            empty="尚無派工"
                            items={project.dispatches}
                            render={(dispatch) => (
                                <>
                                    <div>
                                        <div className="font-medium text-gray-950">
                                            {dispatch.work_item}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500">
                                            {dispatch.scheduled_date} ·{' '}
                                            {dispatch.status}
                                        </div>
                                    </div>
                                    <div className="text-sm text-gray-600">
                                        {dispatch.work_crew?.name || '未指定工班'}
                                    </div>
                                </>
                            )}
                        />

                        {canViewProgressLogs && (
                            <RelatedList
                                title="工程日誌"
                                empty="尚無工程日誌"
                                items={project.progress_logs}
                                render={(log) => (
                                    <>
                                        <div>
                                            <Link
                                                href={route(
                                                    'progress-logs.show',
                                                    log.id,
                                                )}
                                                className="font-medium text-gray-950 hover:text-indigo-700"
                                            >
                                                {log.work_date}
                                            </Link>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {log.work_items ||
                                                    log.dispatch?.work_item ||
                                                    '現場回報'}
                                            </div>
                                        </div>
                                        <div className="text-right text-sm text-gray-600">
                                            <div>{log.progress_percent}%</div>
                                            <div className="mt-1">
                                                照片 {log.photos_count} 張
                                            </div>
                                        </div>
                                    </>
                                )}
                            />
                        )}

                        {canViewFinancialRecords && (
                            <FinancialRecordsList
                                project={project}
                                records={project.financial_records}
                                types={project.financial_record_types}
                                statuses={project.financial_record_statuses}
                                canPreviewInvoice={canPreviewInvoice}
                            />
                        )}

                        {canViewFinancialRecords && (
                            <RelatedList
                                title="請款單 PDF 版本"
                                empty="尚無請款單 PDF 版本"
                                items={project.invoice_document_versions}
                                render={(version) => (
                                    <>
                                        <div>
                                            <div className="font-medium text-gray-950">
                                                v{version.version_number} ·{' '}
                                                {version.file_name}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {version.generated_at || '未填'} ·{' '}
                                                {version.generator?.name || '系統'}
                                            </div>
                                        </div>
                                        <div className="text-sm text-gray-600">
                                            {fileSize(version.size)}
                                        </div>
                                    </>
                                )}
                            />
                        )}

                        {canViewInventoryTransactions && (
                            <RelatedList
                                title="庫存異動"
                                empty="尚無庫存異動"
                                items={project.inventory_transactions}
                                render={(transaction) => (
                                    <>
                                        <div>
                                            <div className="font-medium text-gray-950">
                                                {transaction.material?.name ||
                                                    '材料'}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {transaction.type}
                                            </div>
                                        </div>
                                        <div className="text-sm text-gray-600">
                                            {transaction.quantity}{' '}
                                            {transaction.unit}
                                        </div>
                                    </>
                                )}
                            />
                        )}
                    </div>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyProject}>
                                刪除案件
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
        </div>
    );
}

function RelatedList({ title, empty, items, render }) {
    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="text-base font-semibold text-gray-950">{title}</h3>
            <div className="mt-5 divide-y divide-gray-100">
                {items.length === 0 && (
                    <p className="text-sm text-gray-500">{empty}</p>
                )}
                {items.map((item) => (
                    <div
                        key={item.id}
                        className="flex items-center justify-between gap-4 py-3"
                    >
                        {render(item)}
                    </div>
                ))}
            </div>
        </section>
    );
}

function ChangeOrdersList({ project, orders, statuses, canCreate }) {
    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="flex items-center justify-between gap-4">
                <h3 className="text-base font-semibold text-gray-950">
                    工程變更追加單
                </h3>
                {canCreate && (
                    <Link
                        href={`${route('project-change-orders.create')}?project_id=${project.id}`}
                        className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                    >
                        新增
                    </Link>
                )}
            </div>
            <div className="mt-5 divide-y divide-gray-100">
                {orders.length === 0 && (
                    <p className="text-sm text-gray-500">尚無追加單</p>
                )}
                {orders.map((order) => (
                    <div
                        key={order.id}
                        className="flex items-start justify-between gap-4 py-3"
                    >
                        <div>
                            <Link
                                href={route(
                                    'project-change-orders.show',
                                    order.id,
                                )}
                                className="font-medium text-gray-950 hover:text-indigo-700"
                            >
                                {order.title}
                            </Link>
                            <div className="mt-1 text-sm text-gray-500">
                                {statuses[order.status] ?? order.status} · 提出{' '}
                                {order.requested_date || '未填'}
                            </div>
                            {order.financial_record && (
                                <div className="mt-1 text-xs text-emerald-700">
                                    已建立追加款
                                </div>
                            )}
                        </div>
                        <div className="text-sm text-gray-600">
                            {money(order.amount)}
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function FinancialRecordsList({
    project,
    records,
    types,
    statuses,
    canPreviewInvoice,
}) {
    const eligibleIds = useMemo(
        () =>
            records
                .filter((record) => record.invoice_eligible)
                .map((record) => record.id),
        [records],
    );
    const [selectedIds, setSelectedIds] = useState([]);
    const selectedTotal = records
        .filter((record) => selectedIds.includes(record.id))
        .reduce((sum, record) => sum + Number(record.amount ?? 0), 0);
    const invoiceUrl = invoicePdfUrl(project.id, selectedIds);

    const toggleRecord = (record) => {
        if (!record.invoice_eligible) return;

        setSelectedIds((current) =>
            current.includes(record.id)
                ? current.filter((id) => id !== record.id)
                : [...current, record.id],
        );
    };

    const toggleAll = () => {
        setSelectedIds((current) =>
            current.length === eligibleIds.length ? [] : eligibleIds,
        );
    };

    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <div>
                    <h3 className="text-base font-semibold text-gray-950">
                        收款紀錄
                    </h3>
                    {selectedIds.length > 0 && (
                        <p className="mt-1 text-sm text-gray-500">
                            已選 {selectedIds.length} 筆，合計{' '}
                            {money(selectedTotal)}
                        </p>
                    )}
                </div>
                {canPreviewInvoice && (
                    <a
                        href={invoiceUrl}
                        target="_blank"
                        rel="noreferrer"
                        className={
                            selectedIds.length === 0
                                ? 'pointer-events-none opacity-50'
                                : ''
                        }
                    >
                        <SecondaryButton
                            type="button"
                            disabled={selectedIds.length === 0}
                        >
                            預覽合併請款單 PDF
                        </SecondaryButton>
                    </a>
                )}
            </div>

            <div className="mt-5 divide-y divide-gray-100">
                {records.length === 0 && (
                    <p className="text-sm text-gray-500">尚無收款紀錄</p>
                )}

                {records.length > 0 && eligibleIds.length > 0 && (
                    <label className="flex items-center gap-3 py-3 text-sm text-gray-600">
                        <input
                            type="checkbox"
                            checked={
                                selectedIds.length === eligibleIds.length &&
                                eligibleIds.length > 0
                            }
                            onChange={toggleAll}
                            className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        />
                        全選待收 / 逾期款項
                    </label>
                )}

                {records.map((record) => (
                    <div
                        key={record.id}
                        className="flex items-start justify-between gap-4 py-3"
                    >
                        <div className="flex items-start gap-3">
                            {canPreviewInvoice && (
                                <input
                                    type="checkbox"
                                    checked={selectedIds.includes(record.id)}
                                    disabled={!record.invoice_eligible}
                                    onChange={() => toggleRecord(record)}
                                    className="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-40"
                                />
                            )}
                            <div>
                                <div className="font-medium text-gray-950">
                                    {record.title}
                                </div>
                                <div className="mt-1 text-sm text-gray-500">
                                    {types[record.type] ?? record.type} ·{' '}
                                    {statuses[record.status] ?? record.status}
                                </div>
                                <div className="mt-1 text-xs text-gray-400">
                                    應收 {record.due_date || '未填'}
                                    {!record.invoice_eligible &&
                                        ' · 不可列入請款單'}
                                </div>
                            </div>
                        </div>
                        <div className="text-sm text-gray-600">
                            {money(record.amount)}
                        </div>
                    </div>
                ))}
            </div>
        </section>
    );
}

function invoicePdfUrl(projectId, recordIds) {
    const params = new URLSearchParams();
    recordIds.forEach((id) => params.append('financial_record_ids[]', id));

    return `${route('projects.invoice-pdf', projectId)}?${params.toString()}`;
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function fileSize(value) {
    const size = Number(value ?? 0);
    if (size >= 1024 * 1024) {
        return `${(size / 1024 / 1024).toFixed(1)} MB`;
    }

    if (size >= 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${size} B`;
}
