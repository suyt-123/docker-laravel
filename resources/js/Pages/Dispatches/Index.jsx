import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ dispatches, filters, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.dispatches.create);
    const canUpdate = can(CAPABILITIES.dispatches.update);
    const canDelete = can(CAPABILITIES.dispatches.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        date: filters.date ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('dispatches.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyDispatch = (dispatch) => {
        if (!window.confirm(`確定要刪除「${dispatch.work_item}」派工嗎？`)) {
            return;
        }

        router.delete(route('dispatches.destroy', dispatch.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        派工管理
                    </h2>
                    {canCreate && (
                        <Link href={route('dispatches.create')}>
                            <PrimaryButton>新增派工</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="派工管理" />

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
                            placeholder="搜尋工項、案件、工班"
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
                        <TextInput
                            type="date"
                            value={data.date}
                            onChange={(event) =>
                                setData('date', event.target.value)
                            }
                        />
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('dispatches.index')}>
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
                                        <Header>派工</Header>
                                        <Header>案件</Header>
                                        <Header>工班</Header>
                                        <Header>日期時間</Header>
                                        <Header align="right">師傅</Header>
                                        <Header>狀態</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {dispatches.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 7 : 6}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有派工
                                            </td>
                                        </tr>
                                    )}

                                    {dispatches.data.map((dispatch) => (
                                        <tr
                                            key={dispatch.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'dispatches.show',
                                                        dispatch.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {dispatch.work_item}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {dispatch.address ||
                                                        '未填地址'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <Link
                                                    href={route(
                                                        'projects.show',
                                                        dispatch.project.id,
                                                    )}
                                                    className="font-medium hover:text-indigo-700"
                                                >
                                                    {dispatch.project.project_no}
                                                </Link>
                                                <div className="mt-1 text-gray-500">
                                                    {dispatch.project.name}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {dispatch.work_crew?.name ||
                                                    '未指定'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>
                                                    {dispatch.scheduled_date}
                                                </div>
                                                <div className="mt-1 text-gray-500">
                                                    {[dispatch.start_time, dispatch.end_time]
                                                        .filter(Boolean)
                                                        .join(' - ') || '未填時間'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {dispatch.workers_count}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {statuses[dispatch.status] ??
                                                    dispatch.status}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'dispatches.edit',
                                                                    dispatch.id,
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
                                                                    destroyDispatch(
                                                                        dispatch,
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

                    {dispatches.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {dispatches.links.map((link) => (
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
