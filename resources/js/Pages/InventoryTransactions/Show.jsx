import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ transaction, types }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.inventoryTransactions.update);
    const canDelete = can(CAPABILITIES.inventoryTransactions.delete);

    const destroyTransaction = () => {
        if (!window.confirm('確定要刪除這筆庫存異動嗎？庫存量會回復。')) {
            return;
        }

        router.delete(route('inventory-transactions.destroy', transaction.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {types[transaction.type] ?? transaction.type}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {transaction.material.name}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('inventory-transactions.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link
                                href={route(
                                    'inventory-transactions.edit',
                                    transaction.id,
                                )}
                            >
                                <PrimaryButton>編輯異動</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="庫存異動" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            異動資訊
                        </h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info
                                label="材料"
                                value={`${transaction.material.name}${
                                    transaction.material.spec
                                        ? ` · ${transaction.material.spec}`
                                        : ''
                                }`}
                            />
                            <Info
                                label="工程案件"
                                value={
                                    transaction.project
                                        ? `${transaction.project.project_no} · ${transaction.project.name}`
                                        : null
                                }
                            />
                            <Info
                                label="異動類型"
                                value={types[transaction.type]}
                            />
                            <Info
                                label="異動時間"
                                value={transaction.occurred_at}
                            />
                            <Info
                                label="數量"
                                value={`${number(transaction.quantity)} ${transaction.unit}`}
                            />
                            <Info
                                label="目前材料庫存"
                                value={`${number(transaction.material.current_stock)} ${transaction.material.unit}`}
                            />
                            <Info
                                label="單位成本"
                                value={money(transaction.unit_cost)}
                            />
                            <Info
                                label="成本小計"
                                value={money(transaction.total_cost)}
                            />
                            <Info
                                label="參考單號"
                                value={transaction.reference_no}
                            />
                            <Info label="建立人" value={transaction.creator?.name} />
                            <Info label="備註" value={transaction.note} wide />
                        </dl>
                    </section>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyTransaction}>
                                刪除異動
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

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
