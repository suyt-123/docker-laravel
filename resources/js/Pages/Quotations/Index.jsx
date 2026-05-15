import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ quotations, filters, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.quotations.create);
    const canUpdate = can(CAPABILITIES.quotations.update);
    const canDelete = can(CAPABILITIES.quotations.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('quotations.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyQuotation = (quotation) => {
        if (!window.confirm(`確定要刪除「${quotation.quotation_no}」嗎？`)) {
            return;
        }

        router.delete(route('quotations.destroy', quotation.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        報價單
                    </h2>
                    {canCreate && (
                        <Link href={route('quotations.create')}>
                            <PrimaryButton>新增報價單</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="報價單" />

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
                            placeholder="搜尋報價單號、客戶、案件"
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
                            <Link href={route('quotations.index')}>
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
                                        <Header>報價單</Header>
                                        <Header>客戶 / 案件</Header>
                                        <Header>狀態</Header>
                                        <Header>核准人</Header>
                                        <Header align="right">項目</Header>
                                        <Header align="right">小計</Header>
                                        <Header align="right">總額</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {quotations.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 8 : 7}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有報價單
                                            </td>
                                        </tr>
                                    )}
                                    {quotations.data.map((quotation) => (
                                        <tr
                                            key={quotation.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'quotations.show',
                                                        quotation.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {quotation.quotation_no}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    有效至{' '}
                                                    {quotation.valid_until ||
                                                        '未設定'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <Link
                                                    href={route(
                                                        'customers.show',
                                                        quotation.customer.id,
                                                    )}
                                                    className="font-medium hover:text-indigo-700"
                                                >
                                                    {quotation.customer.name}
                                                </Link>
                                                <div className="mt-1 text-gray-500">
                                                    {quotation.project
                                                        ? `${quotation.project.project_no} · ${quotation.project.name}`
                                                        : '未綁定案件'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {statuses[quotation.status] ??
                                                    quotation.status}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {quotation.approver?.name || '未核准'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {quotation.items_count}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(quotation.subtotal)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-medium text-gray-950">
                                                {money(quotation.total)}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate &&
                                                            quotation.status ===
                                                                'draft' && (
                                                            <Link
                                                                href={route(
                                                                    'quotations.edit',
                                                                    quotation.id,
                                                                )}
                                                                className="font-medium text-indigo-700 hover:text-indigo-900"
                                                            >
                                                                編輯
                                                            </Link>
                                                        )}
                                                        {canDelete &&
                                                            quotation.status ===
                                                                'draft' && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    destroyQuotation(
                                                                        quotation,
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

                    {quotations.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {quotations.links.map((link) => (
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

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
