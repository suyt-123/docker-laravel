import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ customers, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.customers.create);
    const canUpdate = can(CAPABILITIES.customers.update);
    const canDelete = can(CAPABILITIES.customers.delete);
    const canViewContact = can(CAPABILITIES.customers.viewContact);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
    });

    const search = (event) => {
        event.preventDefault();

        get(route('customers.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyCustomer = (customer) => {
        if (!window.confirm(`確定要刪除「${customer.name}」嗎？`)) {
            return;
        }

        router.delete(route('customers.destroy', customer.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        客戶管理
                    </h2>
                    {canCreate && (
                        <Link href={route('customers.create')}>
                            <PrimaryButton>新增客戶</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="客戶管理" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={search}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:flex-row sm:items-center sm:rounded-lg"
                    >
                        <TextInput
                            className="w-full sm:max-w-sm"
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder={
                                canViewContact
                                    ? '搜尋客戶、電話、LINE、統編'
                                    : '搜尋客戶'
                            }
                        />
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('customers.index')}>
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
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            客戶
                                        </th>
                                        {canViewContact && (
                                            <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                聯絡
                                            </th>
                                        )}
                                        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            來源
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            案件
                                        </th>
                                        <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                            報價
                                        </th>
                                        {canManage && (
                                            <th className="px-4 py-3 text-right text-xs font-semibold uppercase tracking-wide text-gray-500">
                                                操作
                                            </th>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {customers.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={
                                                    4 +
                                                    (canViewContact ? 1 : 0) +
                                                    (canManage ? 1 : 0)
                                                }
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有客戶資料
                                            </td>
                                        </tr>
                                    )}

                                    {customers.data.map((customer) => (
                                        <tr
                                            key={customer.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'customers.show',
                                                        customer.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {customer.name}
                                                </Link>
                                                {canViewContact && (
                                                    <div className="mt-1 max-w-md text-sm text-gray-500">
                                                        {customer.address ||
                                                            '未填地址'}
                                                    </div>
                                                )}
                                            </td>
                                            {canViewContact && (
                                                <td className="px-4 py-4 text-sm text-gray-700">
                                                    <div>{customer.phone || '未填電話'}</div>
                                                    <div className="mt-1 text-gray-500">
                                                        {customer.primary_contact?.name
                                                            ? `聯絡人：${customer.primary_contact.name}`
                                                            : '未填主要聯絡人'}
                                                    </div>
                                                    {customer.line_id && (
                                                        <div className="mt-1 text-gray-500">
                                                            LINE：{customer.line_id}
                                                        </div>
                                                    )}
                                                </td>
                                            )}
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {customer.source || '未填'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {customer.projects_count}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {customer.quotations_count}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'customers.edit',
                                                                    customer.id,
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
                                                                    destroyCustomer(
                                                                        customer,
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

                    {customers.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {customers.links.map((link) => (
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
