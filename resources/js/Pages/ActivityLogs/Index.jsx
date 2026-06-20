import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Index({ activityLogs, filters, options }) {
    const { flash = {} } = usePage().props;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        module: filters.module ?? '',
        action: filters.action ?? '',
        actor_id: filters.actor_id ?? '',
        date: filters.date ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        get(route('activity-logs.index'), {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    操作紀錄
                </h2>
            }
        >
            <Head title="操作紀錄" />

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
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋目標、說明、使用者..."
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.module}
                            onChange={(event) =>
                                setData('module', event.target.value)
                            }
                        >
                            <option value="">全部模組</option>
                            {options.modules.map((module) => (
                                <option
                                    key={module.value}
                                    value={module.value}
                                >
                                    {module.label}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.action}
                            onChange={(event) =>
                                setData('action', event.target.value)
                            }
                        >
                            <option value="">全部動作</option>
                            {Object.entries(options.actions).map(
                                ([value, label]) => (
                                    <option key={value} value={value}>
                                        {label}
                                    </option>
                                ),
                            )}
                        </select>
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.actor_id}
                            onChange={(event) =>
                                setData('actor_id', event.target.value)
                            }
                        >
                            <option value="">全部使用者</option>
                            {options.actors.map((actor) => (
                                <option key={actor.id} value={actor.id}>
                                    {actor.name} · {actor.email}
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
                            <Link href={route('activity-logs.index')}>
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
                                        <Header>時間</Header>
                                        <Header>使用者</Header>
                                        <Header>動作</Header>
                                        <Header>模組 / 目標</Header>
                                        <Header>IP</Header>
                                        <Header align="right">操作</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {activityLogs.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有操作紀錄
                                            </td>
                                        </tr>
                                    )}

                                    {activityLogs.data.map((log) => (
                                        <tr
                                            key={log.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <Cell>{log.created_at}</Cell>
                                            <Cell>
                                                <div className="font-medium text-gray-950">
                                                    {log.actor?.name ||
                                                        '系統'}
                                                </div>
                                                <div className="mt-1 text-xs text-gray-500">
                                                    {log.actor?.email}
                                                </div>
                                            </Cell>
                                            <Cell>
                                                <span
                                                    className={[
                                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                                        tone(log.action),
                                                    ].join(' ')}
                                                >
                                                    {log.action_label}
                                                </span>
                                                <div className="mt-2 text-xs text-gray-500">
                                                    {log.event}
                                                </div>
                                            </Cell>
                                            <Cell>
                                                <div className="font-medium text-gray-950">
                                                    {log.module_label ||
                                                        log.module}
                                                </div>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {log.subject_label ||
                                                        `${log.subject_name ?? 'Record'} #${log.subject_id}`}
                                                </div>
                                            </Cell>
                                            <Cell>{log.ip_address || '未記錄'}</Cell>
                                            <Cell align="right">
                                                <Link
                                                    href={route(
                                                        'activity-logs.show',
                                                        log.id,
                                                    )}
                                                    className="font-medium text-indigo-700 hover:text-indigo-900"
                                                >
                                                    查看
                                                </Link>
                                            </Cell>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <Pagination links={activityLogs.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) {
    return (
        <th
            className={[
                'px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-500',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function Cell({ children, align = 'left' }) {
    return (
        <td
            className={[
                'whitespace-nowrap px-4 py-4 text-sm text-gray-700',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </td>
    );
}

function Pagination({ links }) {
    if (!links || links.length <= 3) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {links.map((link) => (
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
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}

function tone(action) {
    return {
        create: 'bg-emerald-50 text-emerald-700',
        update: 'bg-indigo-50 text-indigo-700',
        delete: 'bg-red-50 text-red-700',
    }[action] ?? 'bg-gray-100 text-gray-700';
}
