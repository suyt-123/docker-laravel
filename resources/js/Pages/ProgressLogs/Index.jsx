import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ progressLogs, filters, options }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.progressLogs.create);
    const canUpdate = can(CAPABILITIES.progressLogs.update);
    const canDelete = can(CAPABILITIES.progressLogs.delete);
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        project_id: filters.project_id ?? '',
        date: filters.date ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('progress-logs.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyLog = (log) => {
        if (!window.confirm(`確定要刪除 ${log.work_date} 的工程日誌嗎？`)) {
            return;
        }

        router.delete(route('progress-logs.destroy', log.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        工程日誌
                    </h2>
                    {canCreate && (
                        <Link href={route('progress-logs.create')}>
                            <PrimaryButton>新增日誌</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="工程日誌" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="grid gap-3 bg-white p-4 shadow-sm sm:rounded-lg md:grid-cols-[1fr_220px_180px_auto]"
                    >
                        <TextInput
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋案件、工項、異常..."
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.project_id}
                            onChange={(event) =>
                                setData('project_id', event.target.value)
                            }
                        >
                            <option value="">全部案件</option>
                            {options.projects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.project_no} · {project.name}
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
                            <Link href={route('progress-logs.index')}>
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
                                        <Th>日期 / 案件</Th>
                                        <Th>工項</Th>
                                        <Th>進度</Th>
                                        <Th>現場</Th>
                                        <Th>照片</Th>
                                        <Th className="text-right">操作</Th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {progressLogs.data.map((log) => (
                                        <tr key={log.id}>
                                            <Td>
                                                <Link
                                                    href={route(
                                                        'progress-logs.show',
                                                        log.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {log.work_date}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {log.project?.project_no} ·{' '}
                                                    {log.project?.name}
                                                </div>
                                            </Td>
                                            <Td>
                                                <div className="font-medium text-gray-900">
                                                    {log.work_items ||
                                                        log.dispatch
                                                            ?.work_item ||
                                                        '未填'}
                                                </div>
                                                {log.issue && (
                                                    <div className="mt-1 text-sm text-red-700">
                                                        異常：{log.issue}
                                                    </div>
                                                )}
                                            </Td>
                                            <Td>
                                                <div className="flex min-w-32 items-center gap-3">
                                                    <div className="h-2 flex-1 rounded-full bg-gray-100">
                                                        <div
                                                            className="h-2 rounded-full bg-indigo-600"
                                                            style={{
                                                                width: `${log.progress_percent}%`,
                                                            }}
                                                        />
                                                    </div>
                                                    <span className="text-sm text-gray-700">
                                                        {log.progress_percent}%
                                                    </span>
                                                </div>
                                            </Td>
                                            <Td>
                                                <div className="text-sm text-gray-700">
                                                    {log.weather || '未填天氣'} ·{' '}
                                                    {log.worker_count ?? 0} 人
                                                </div>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {log.worker?.name ||
                                                        log.creator?.name ||
                                                        '未填回報人'}
                                                </div>
                                            </Td>
                                            <Td>{log.photos_count}</Td>
                                            <Td>
                                                <div className="flex justify-end gap-2">
                                                    <Link
                                                        href={route(
                                                            'progress-logs.show',
                                                            log.id,
                                                        )}
                                                    >
                                                        <SecondaryButton type="button">
                                                            查看
                                                        </SecondaryButton>
                                                    </Link>
                                                    {canUpdate && (
                                                        <Link
                                                            href={route(
                                                                'progress-logs.edit',
                                                                log.id,
                                                            )}
                                                        >
                                                            <PrimaryButton>
                                                                編輯
                                                            </PrimaryButton>
                                                        </Link>
                                                    )}
                                                    {canDelete && (
                                                        <DangerButton
                                                            onClick={() =>
                                                                destroyLog(log)
                                                            }
                                                        >
                                                            刪除
                                                        </DangerButton>
                                                    )}
                                                </div>
                                            </Td>
                                        </tr>
                                    ))}
                                    {progressLogs.data.length === 0 && (
                                        <tr>
                                            <Td colSpan="6">
                                                <div className="py-8 text-center text-gray-500">
                                                    尚無工程日誌
                                                </div>
                                            </Td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <Pagination links={progressLogs.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Th({ children, className = '' }) {
    return (
        <th
            className={`px-5 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 ${className}`}
        >
            {children}
        </th>
    );
}

function Td({ children, className = '', ...props }) {
    return (
        <td
            className={`whitespace-nowrap px-5 py-4 text-sm text-gray-700 ${className}`}
            {...props}
        >
            {children}
        </td>
    );
}

function Pagination({ links }) {
    if (!links?.length) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {links.map((link) =>
                link.url ? (
                    <Link
                        key={link.label}
                        href={link.url}
                        className={`rounded-md border px-3 py-2 text-sm ${
                            link.active
                                ? 'border-indigo-600 bg-indigo-600 text-white'
                                : 'border-gray-200 bg-white text-gray-700 hover:border-indigo-300'
                        }`}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={link.label}
                        className="rounded-md border border-gray-200 bg-gray-50 px-3 py-2 text-sm text-gray-400"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </div>
    );
}
