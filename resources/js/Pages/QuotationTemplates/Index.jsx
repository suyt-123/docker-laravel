import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ templates, filters, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.quotationTemplates.create);
    const canUpdate = can(CAPABILITIES.quotationTemplates.update);
    const canDelete = can(CAPABILITIES.quotationTemplates.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('quotation-templates.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyTemplate = (template) => {
        if (window.confirm(`確定要刪除「${template.name}」嗎？`)) {
            router.delete(route('quotation-templates.destroy', template.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        報價模板
                    </h2>
                    {canCreate && (
                        <Link href={route('quotation-templates.create')}>
                            <PrimaryButton>新增模板</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="報價模板" />
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
                            placeholder="搜尋模板名稱、類型"
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
                            <Link href={route('quotation-templates.index')}>
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
                                        <Header>模板</Header>
                                        <Header>狀態</Header>
                                        <Header align="right">明細</Header>
                                        <Header align="right">預設利潤率</Header>
                                        <Header align="right">稅金 / 折扣</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {templates.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 6 : 5}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有報價模板
                                            </td>
                                        </tr>
                                    )}
                                    {templates.data.map((template) => (
                                        <tr
                                            key={template.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'quotation-templates.show',
                                                        template.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {template.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {template.type || '未分類'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {statuses[template.status] ??
                                                    template.status}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {template.items_count}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {template.profit_rate}%
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(template.tax)} /{' '}
                                                {money(template.discount)}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'quotation-templates.edit',
                                                                    template.id,
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
                                                                    destroyTemplate(
                                                                        template,
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
