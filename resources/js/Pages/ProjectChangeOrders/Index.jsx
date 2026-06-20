import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ orders, filters, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.projectChangeOrders.create);
    const canUpdate = can(CAPABILITIES.projectChangeOrders.update);
    const canDelete = can(CAPABILITIES.projectChangeOrders.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('project-change-orders.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyOrder = (order) => {
        if (window.confirm(`確定要刪除「${order.title}」嗎？`)) {
            router.delete(route('project-change-orders.destroy', order.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        工程變更追加單
                    </h2>
                    {canCreate && (
                        <Link href={route('project-change-orders.create')}>
                            <PrimaryButton>新增追加單</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="工程變更追加單" />

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
                            placeholder="搜尋追加項目、案件、客戶"
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
                            <Link href={route('project-change-orders.index')}>
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
                                        <Header>追加項目</Header>
                                        <Header>案件 / 客戶</Header>
                                        <Header>日期</Header>
                                        <Header align="right">追加金額</Header>
                                        <Header>狀態</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {orders.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 6 : 5}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有工程變更追加單
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
                                                        'project-change-orders.show',
                                                        order.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {order.title}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {order.financial_record
                                                        ? '已建立追加款'
                                                        : '尚未建立追加款'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <Link
                                                    href={route(
                                                        'projects.show',
                                                        order.project.id,
                                                    )}
                                                    className="font-medium hover:text-indigo-700"
                                                >
                                                    {order.project.project_no}
                                                </Link>
                                                <div className="mt-1 text-gray-500">
                                                    {order.project.name} ·{' '}
                                                    {order.project.customer?.name ||
                                                        '未填客戶'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>
                                                    提出{' '}
                                                    {order.requested_date ||
                                                        '未填'}
                                                </div>
                                                <div className="mt-1 text-gray-500">
                                                    確認{' '}
                                                    {order.approved_date ||
                                                        '未確認'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-medium text-gray-950">
                                                {money(order.amount)}
                                            </td>
                                            <td className="px-4 py-4 text-sm">
                                                <Status
                                                    value={order.status}
                                                    label={
                                                        statuses[
                                                            order.status
                                                        ] ?? order.status
                                                    }
                                                />
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate &&
                                                            !order.financial_record &&
                                                            order.status === 'draft' && (
                                                                <Link
                                                                    href={route(
                                                                        'project-change-orders.edit',
                                                                        order.id,
                                                                    )}
                                                                    className="font-medium text-indigo-700 hover:text-indigo-900"
                                                                >
                                                                    編輯
                                                                </Link>
                                                            )}
                                                        {canDelete &&
                                                            !order.financial_record &&
                                                            ['draft', 'cancelled'].includes(
                                                                order.status,
                                                            ) && (
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
            className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${
                align === 'right' ? 'text-right' : 'text-left'
            }`}
        >
            {children}
        </th>
    );
}

function Status({ value, label }) {
    const color =
        value === 'converted'
            ? 'bg-emerald-50 text-emerald-700'
            : value === 'customer_confirmed'
              ? 'bg-sky-50 text-sky-700'
            : value === 'approved'
              ? 'bg-indigo-50 text-indigo-700'
              : value === 'pending_approval'
                ? 'bg-amber-50 text-amber-700'
              : value === 'cancelled'
                ? 'bg-red-50 text-red-700'
                : 'bg-gray-100 text-gray-700';

    return (
        <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${color}`}>
            {label}
        </span>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
