import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ projects, filters, statuses }) {
    const { flash = {} } = usePage().props;
    const { can, canViewProjectFinancials } = useAuthorization();
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
    });
    const canCreate = can(CAPABILITIES.projects.create);
    const canUpdate = can(CAPABILITIES.projects.update);
    const canDelete = can(CAPABILITIES.projects.delete);
    const canManage = canUpdate || canDelete;
    const showFinancials = canViewProjectFinancials();
    const canViewCustomerContact = can(CAPABILITIES.customers.viewContact);
    const columnCount = 4 + (showFinancials ? 2 : 0) + (canManage ? 1 : 0);

    const submit = (event) => {
        event.preventDefault();

        get(route('projects.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyProject = (project) => {
        if (!window.confirm(`確定要刪除「${project.name}」嗎？`)) {
            return;
        }

        router.delete(route('projects.destroy', project.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        工程案件
                    </h2>
                    {canCreate && (
                        <Link href={route('projects.create')}>
                            <PrimaryButton>新增案件</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="工程案件" />

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
                            placeholder="搜尋案件編號、名稱、客戶、類型"
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
                            <Link href={route('projects.index')}>
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
                                        <Header>案件</Header>
                                        <Header>客戶</Header>
                                        <Header>狀態</Header>
                                        <Header>日期</Header>
                                        {showFinancials && (
                                            <>
                                                <Header align="right">
                                                    合約金額
                                                </Header>
                                                <Header align="right">毛利</Header>
                                            </>
                                        )}
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {projects.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={columnCount}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有工程案件
                                            </td>
                                        </tr>
                                    )}

                                    {projects.data.map((project) => (
                                        <tr
                                            key={project.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'projects.show',
                                                        project.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {project.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {project.project_no} ·{' '}
                                                    {project.type || '未分類'}
                                                </div>
                                                <div className="mt-1 max-w-md text-sm text-gray-500">
                                                    {project.address || '未填地址'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <Link
                                                    href={route(
                                                        'customers.show',
                                                        project.customer.id,
                                                    )}
                                                    className="font-medium hover:text-indigo-700"
                                                >
                                                    {project.customer.name}
                                                </Link>
                                                {canViewCustomerContact && (
                                                    <div className="mt-1 text-gray-500">
                                                        {project.customer.phone ||
                                                            '未填電話'}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-sm">
                                                <StatusBadge
                                                    label={
                                                        statuses[
                                                            project.status
                                                        ] ?? project.status
                                                    }
                                                />
                                                <div className="mt-2 text-gray-500">
                                                    {project.manager?.name ||
                                                        '未指定負責人'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>
                                                    {project.start_date || '未排程'}
                                                </div>
                                                <div className="mt-1 text-gray-500">
                                                    {project.end_date || ''}
                                                </div>
                                            </td>
                                            {showFinancials && (
                                                <>
                                                    <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                        {money(
                                                            project.contract_amount,
                                                        )}
                                                    </td>
                                                    <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                        {money(
                                                            project.gross_profit,
                                                        )}
                                                    </td>
                                                </>
                                            )}
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'projects.edit',
                                                                    project.id,
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
                                                                    destroyProject(
                                                                        project,
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

                    {projects.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {projects.links.map((link) => (
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

function StatusBadge({ label }) {
    return (
        <span className="inline-flex rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">
            {label}
        </span>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
