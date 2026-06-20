import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ order, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.projectChangeOrders.update);
    const canDelete = can(CAPABILITIES.projectChangeOrders.delete);
    const canConvert = can(
        CAPABILITIES.projectChangeOrders.convertFinancialRecord,
    );
    const isLocked = Boolean(order.financial_record);

    const destroyOrder = () => {
        if (window.confirm(`確定要刪除「${order.title}」嗎？`)) {
            router.delete(route('project-change-orders.destroy', order.id));
        }
    };

    const convertOrder = () => {
        if (!window.confirm('確定要將此追加單轉成追加款收款紀錄嗎？')) {
            return;
        }

        router.post(
            route('project-change-orders.convert-financial-record', order.id),
        );
    };

    const workflowAction = (routeName, message) => {
        if (!window.confirm(message)) {
            return;
        }

        router.post(route(routeName, order.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {order.title}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {statuses[order.status] ?? order.status}
                        </p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Link href={route('project-change-orders.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {order.financial_record && (
                            <Link
                                href={route(
                                    'financial-records.show',
                                    order.financial_record.id,
                                )}
                            >
                                <SecondaryButton type="button">
                                    前往追加款
                                </SecondaryButton>
                            </Link>
                        )}
                        {order.quotation && (
                            <Link href={route('quotations.show', order.quotation.id)}>
                                <SecondaryButton type="button">
                                    前往追加報價
                                </SecondaryButton>
                            </Link>
                        )}
                        {order.can_create_quotation && (
                            <SecondaryButton
                                type="button"
                                onClick={() =>
                                    workflowAction(
                                        'project-change-orders.create-quotation',
                                        '確定要由此追加單建立正式追加報價單嗎？',
                                    )
                                }
                            >
                                建立追加報價
                            </SecondaryButton>
                        )}
                        {order.can_submit_review && (
                            <PrimaryButton
                                onClick={() =>
                                    workflowAction(
                                        'project-change-orders.submit-review',
                                        '確定要送主管核准嗎？',
                                    )
                                }
                            >
                                送審
                            </PrimaryButton>
                        )}
                        {order.can_approve && (
                            <PrimaryButton
                                onClick={() =>
                                    workflowAction(
                                        'project-change-orders.approve',
                                        '確定要核准此追加單嗎？',
                                    )
                                }
                            >
                                主管核准
                            </PrimaryButton>
                        )}
                        {order.can_confirm_customer && (
                            <PrimaryButton
                                onClick={() =>
                                    workflowAction(
                                        'project-change-orders.confirm-customer',
                                        '確定客戶已確認此追加單嗎？',
                                    )
                                }
                            >
                                客戶已確認
                            </PrimaryButton>
                        )}
                        {canConvert && order.can_convert && (
                            <PrimaryButton onClick={convertOrder}>
                                轉追加款
                            </PrimaryButton>
                        )}
                        {canUpdate && !isLocked && (
                            <Link
                                href={route(
                                    'project-change-orders.edit',
                                    order.id,
                                )}
                            >
                                <PrimaryButton>編輯追加單</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={order.title} />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            追加單資訊
                        </h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info
                                label="工程案件"
                                value={`${order.project.project_no} · ${order.project.name}`}
                            />
                            <Info
                                label="客戶"
                                value={order.project.customer?.name}
                            />
                            <Info
                                label="狀態"
                                value={statuses[order.status] ?? order.status}
                            />
                            <Info
                                label="追加金額"
                                value={money(order.amount)}
                            />
                            <Info
                                label="正式追加報價"
                                value={
                                    order.requires_formal_quotation
                                        ? order.quotation
                                            ? `${order.quotation.quotation_no} · ${order.quotation.status}`
                                            : '需要，尚未建立'
                                        : '不需要'
                                }
                            />
                            <Info
                                label="提出日期"
                                value={order.requested_date}
                            />
                            <Info
                                label="送審時間"
                                value={order.submitted_at}
                            />
                            <Info
                                label="主管核准"
                                value={
                                    order.approver
                                        ? `${order.approver.name} · ${order.approved_at || '未填'}`
                                        : order.approved_at
                                }
                            />
                            <Info
                                label="客戶確認日期"
                                value={order.approved_date}
                            />
                            <Info
                                label="客戶確認時間"
                                value={order.customer_confirmed_at}
                            />
                            <Info
                                label="追加款應收日期"
                                value={order.due_date}
                            />
                            <Info
                                label="建立人"
                                value={order.creator?.name}
                            />
                            <Info
                                label="追加內容"
                                value={order.description}
                                wide
                            />
                            <Info
                                label="客戶確認備註"
                                value={order.customer_note}
                                wide
                            />
                            <Info
                                label="內部備註"
                                value={order.internal_note}
                                wide
                            />
                        </dl>
                    </section>

                    {!isLocked && (canDelete || order.can_cancel) && (
                        <div className="flex justify-end gap-3">
                            {order.can_cancel && order.status !== 'cancelled' && (
                                <SecondaryButton
                                    type="button"
                                    onClick={() =>
                                        workflowAction(
                                            'project-change-orders.cancel',
                                            '確定要取消此追加單嗎？',
                                        )
                                    }
                                >
                                    取消追加單
                                </SecondaryButton>
                            )}
                            {canDelete && (
                                <DangerButton onClick={destroyOrder}>
                                    刪除追加單
                                </DangerButton>
                            )}
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

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
