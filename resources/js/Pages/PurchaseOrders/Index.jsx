import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ orders, filters, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.purchaseOrders.create);
    const canUpdate = can(CAPABILITIES.purchaseOrders.update);
    const canDelete = can(CAPABILITIES.purchaseOrders.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('purchase-orders.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyOrder = (order) => {
        if (window.confirm(`確定要刪除「${order.purchase_order_no}」嗎？`)) {
            router.delete(route('purchase-orders.destroy', order.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        採購單
                    </h2>
                    {canCreate && (
                        <Link href={route('purchase-orders.create')}>
                            <PrimaryButton>新增採購單</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="採購單" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center"
                    >
                        <TextInput
                            className="w-full lg:max-w-sm"
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋採購單號、供應商"
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                        >
                            <option value="">全部狀態</option>
                            {Object.entries(statuses).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('purchase-orders.index')}>
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
                                        <Header>採購單</Header>
                                        <Header>供應商</Header>
                                        <Header>狀態</Header>
                                        <Header>日期</Header>
                                        <Header align="right">項目</Header>
                                        <Header align="right">總額</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {orders.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 7 : 6}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有採購單
                                            </td>
                                        </tr>
                                    )}
                                    {orders.data.map((order) => (
                                        <tr
                                            key={order.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'purchase-orders.show',
                                                        order.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {order.purchase_order_no}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {order.supplier?.name}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {statuses[order.status] ??
                                                    order.status}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>
                                                    採購{' '}
                                                    {order.ordered_date || '未填'}
                                                </div>
                                                <div className="mt-1 text-gray-500">
                                                    到貨{' '}
                                                    {order.expected_date || '未填'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {order.items_count}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-medium text-gray-950">
                                                {money(order.total)}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate &&
                                                            ![
                                                                'partially_received',
                                                                'completed',
                                                                'cancelled',
                                                            ].includes(
                                                                order.status,
                                                            ) && (
                                                                <Link
                                                                    href={route(
                                                                        'purchase-orders.edit',
                                                                        order.id,
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
                                                                    destroyOrder(
                                                                        order,
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

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
