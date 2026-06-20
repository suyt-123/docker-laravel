import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Show({ activityLog }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            操作紀錄詳情
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {activityLog.created_at}
                        </p>
                    </div>
                    <Link href={route('activity-logs.index')}>
                        <SecondaryButton type="button">返回列表</SecondaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="操作紀錄詳情" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-5 lg:grid-cols-3">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                            <h3 className="text-base font-semibold text-gray-950">
                                紀錄資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="使用者"
                                    value={
                                        activityLog.actor
                                            ? `${activityLog.actor.name} (${activityLog.actor.email})`
                                            : '系統'
                                    }
                                />
                                <Info
                                    label="動作"
                                    value={activityLog.action_label}
                                />
                                <Info
                                    label="模組"
                                    value={activityLog.module_label}
                                />
                                <Info
                                    label="目標"
                                    value={
                                        activityLog.subject_label ||
                                        `${activityLog.subject_name ?? 'Record'} #${activityLog.subject_id}`
                                    }
                                />
                                <Info
                                    label="事件"
                                    value={activityLog.event}
                                />
                                <Info
                                    label="IP"
                                    value={activityLog.ip_address}
                                />
                                <Info
                                    label="Request ID"
                                    value={activityLog.request_id}
                                    wide
                                />
                                <Info
                                    label="User Agent"
                                    value={activityLog.user_agent}
                                    wide
                                />
                            </dl>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                說明
                            </h3>
                            <p className="mt-5 whitespace-pre-line text-sm text-gray-700">
                                {activityLog.description || '未記錄'}
                            </p>
                        </section>
                    </div>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <JsonPanel
                            title="修改前"
                            value={activityLog.old_values}
                        />
                        <JsonPanel
                            title="修改後"
                            value={activityLog.new_values}
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line break-words text-sm text-gray-950">
                {value || '未記錄'}
            </dd>
        </div>
    );
}

function JsonPanel({ title, value }) {
    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="text-base font-semibold text-gray-950">{title}</h3>
            <pre className="mt-5 max-h-[520px] overflow-auto rounded-md bg-slate-950 p-4 text-xs leading-6 text-slate-100">
                {value ? JSON.stringify(value, null, 2) : 'null'}
            </pre>
        </section>
    );
}
