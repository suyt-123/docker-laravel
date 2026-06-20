import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ transactions, filters, types }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.inventoryTransactions.create);
    const canUpdate = can(CAPABILITIES.inventoryTransactions.update);
    const canDelete = can(CAPABILITIES.inventoryTransactions.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        type: filters.type ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('inventory-transactions.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyTransaction = (transaction) => {
        if (!window.confirm('確定要刪除這筆庫存異動嗎？庫存量會回復。')) {
            return;
        }

        router.delete(route('inventory-transactions.destroy', transaction.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        庫存異動
                    </h2>
                    {canCreate && (
                        <Link href={route('inventory-transactions.create')}>
                            <PrimaryButton>新增異動</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="庫存異動" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:flex-wrap lg:items-center"
                    >
                        <TextInput
                            className="w-full lg:max-w-sm"
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋材料、規格、案件、參考單號"
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(event) =>
                                setData('type', event.target.value)
                            }
                        >
                            <option value="">全部類型</option>
                            {Object.entries(types).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('inventory-transactions.index')}>
                                <SecondaryButton type="button">
                                    清除
                                </SecondaryButton>
                            </Link>
                        </div>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>材料</Header>
                                        <Header>類型</Header>
                                        <Header>工程案件</Header>
                                        <Header>時間 / 單號</Header>
                                        <Header align="right">數量</Header>
                                        <Header align="right">成本</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {transactions.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 7 : 6}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有庫存異動
                                            </td>
                                        </tr>
                                    )}

                                    {transactions.data.map((transaction) => (
                                        <tr
                                            key={transaction.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'inventory-transactions.show',
                                                        transaction.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {transaction.material.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {transaction.material.spec ||
                                                        '未填規格'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {types[transaction.type] ??
                                                    transaction.type}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {transaction.project
                                                    ? `${transaction.project.project_no} · ${transaction.project.name}`
                                                    : '未綁定案件'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>
                                                    {transaction.occurred_at ||
                                                        '未填'}
                                                </div>
                                                <div className="mt-1 text-gray-500">
                                                    {transaction.reference_no ||
                                                        '無參考單號'}
                                                </div>
                                            </td>
                                            <td
                                                className={[
                                                    'px-4 py-4 text-right text-sm font-medium',
                                                    isIncrease(transaction.type)
                                                        ? 'text-emerald-700'
                                                        : 'text-red-700',
                                                ].join(' ')}
                                            >
                                                {isIncrease(transaction.type)
                                                    ? '+'
                                                    : '-'}
                                                {number(transaction.quantity)}{' '}
                                                {transaction.unit}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(transaction.total_cost)}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'inventory-transactions.edit',
                                                                    transaction.id,
                                                                )}
                                                                className="font-medium text-indigo-700 hover:text-indigo-900"
                                                            >
                                                                編輯
                                                            </Link>
                                                        )}
                                                        {canDelete && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    destroyTransaction(
                                                                        transaction,
                                                                    )
                                                                }
                                                                className="font-medium text-red-700 hover:text-red-900"
                                                            >
                                                                刪除
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {transactions.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {transactions.links.map((link) => (
                                <Link
                                    key={`${link.label}-${link.url}`}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={[
                                        'rounded-md border px-3 py-2 text-sm',
                                        link.active
                                            ? 'border-indigo-600 bg-indigo-600 text-white'
                                            : 'border-gray-300 bg-white text-gray-700',
                                        !link.url
                                            ? 'pointer-events-none opacity-50'
                                            : 'hover:bg-gray-50',
                                    ].join(' ')}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) {
    return (
        <th
            className={[
                'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function isIncrease(type) {
    return ['inbound', 'return', 'adjustment'].includes(type);
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
