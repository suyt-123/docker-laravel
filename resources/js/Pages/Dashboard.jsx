import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link } from '@inertiajs/react';

export default function Dashboard({
    metrics,
    widgets,
    todayDispatches,
    todayProgressLogs,
    unpaidRecords,
    overdueRecords,
    lowStockMaterials,
    projectStatusCounts,
    labels,
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Dashboard
                </h2>
            }
        >
            <Head title="Dashboard" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                        {widgets.dispatches && (
                            <Metric
                                label="今日施工"
                                value={metrics.today_dispatches}
                                href={route('dispatches.index')}
                            />
                        )}
                        {widgets.progressLogs && (
                            <Metric
                                label="今日回報"
                                value={metrics.today_progress_logs}
                                href={route('progress-logs.index', {
                                    date: new Date().toISOString().slice(0, 10),
                                })}
                            />
                        )}
                        {widgets.projects && (
                            <Metric
                                label="進行中案件"
                                value={metrics.active_projects}
                                href={route('projects.index')}
                            />
                        )}
                        {widgets.financialRecords && (
                            <Metric
                                label="未收款"
                                value={money(metrics.unpaid_amount)}
                                href={route('financial-records.index')}
                            />
                        )}
                        {widgets.financialRecords && (
                            <Metric
                                label="逾期款"
                                value={money(metrics.overdue_amount)}
                                href={route('financial-records.index', {
                                    status: 'overdue',
                                })}
                                tone="danger"
                            />
                        )}
                        {widgets.materials && (
                            <Metric
                                label="低庫存"
                                value={metrics.low_stock_count}
                                href={route('materials.index', {
                                    stock: 'low',
                                })}
                                tone="warning"
                            />
                        )}
                    </section>

                    <section className="grid gap-6 xl:grid-cols-3">
                        {widgets.dispatches && (
                            <Panel
                                title="今日施工"
                                href={route('dispatches.index')}
                                empty="今天尚無派工"
                            >
                                {todayDispatches.map((dispatch) => (
                                    <ListRow key={dispatch.id}>
                                        <div>
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
                                                {dispatch.project.project_no} ·{' '}
                                                {dispatch.project.name}
                                            </div>
                                        </div>
                                        <div className="text-right text-sm text-gray-600">
                                            <div>
                                                {[
                                                    dispatch.start_time,
                                                    dispatch.end_time,
                                                ]
                                                    .filter(Boolean)
                                                    .join(' - ') || '未填時間'}
                                            </div>
                                            <div className="mt-1">
                                                {dispatch.work_crew?.name ||
                                                    '未指定工班'}
                                            </div>
                                        </div>
                                    </ListRow>
                                ))}
                            </Panel>
                        )}

                        {widgets.progressLogs && (
                            <Panel
                                title="今日回報"
                                href={route('progress-logs.index', {
                                    date: new Date().toISOString().slice(0, 10),
                                })}
                                empty="今天尚無工程日誌"
                            >
                                {todayProgressLogs.map((log) => (
                                    <ListRow key={log.id}>
                                        <div>
                                            <Link
                                                href={route(
                                                    'progress-logs.show',
                                                    log.id,
                                                )}
                                                className="font-medium text-gray-950 hover:text-indigo-700"
                                            >
                                                {log.work_items ||
                                                    log.dispatch?.work_item ||
                                                    '現場回報'}
                                            </Link>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {log.project.project_no} ·{' '}
                                                {log.project.name}
                                            </div>
                                        </div>
                                        <div className="text-right text-sm text-gray-600">
                                            <div className="font-semibold text-gray-950">
                                                {log.progress_percent}%
                                            </div>
                                            <div className="mt-1">
                                                照片 {log.photos_count} 張
                                            </div>
                                        </div>
                                    </ListRow>
                                ))}
                            </Panel>
                        )}

                        {widgets.financialRecords && (
                            <Panel
                                title="逾期款"
                                href={route('financial-records.index', {
                                    status: 'overdue',
                                })}
                                empty="目前沒有逾期款"
                                danger
                            >
                                {overdueRecords.map((record) => (
                                    <FinancialRow
                                        key={record.id}
                                        record={record}
                                        labels={labels}
                                    />
                                ))}
                            </Panel>
                        )}

                        {widgets.materials && (
                            <Panel
                                title="低庫存"
                                href={route('materials.index', {
                                    stock: 'low',
                                })}
                                empty="目前沒有低庫存材料"
                                warning
                            >
                                {lowStockMaterials.map((material) => (
                                    <ListRow key={material.id}>
                                        <div>
                                            <Link
                                                href={route(
                                                    'materials.show',
                                                    material.id,
                                                )}
                                                className="font-medium text-gray-950 hover:text-indigo-700"
                                            >
                                                {material.name}
                                            </Link>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {material.spec ||
                                                    material.category?.name ||
                                                    '未分類'}
                                            </div>
                                        </div>
                                        <div className="text-right text-sm">
                                            <div className="font-semibold text-red-700">
                                                {number(material.current_stock)}{' '}
                                                {material.unit}
                                            </div>
                                            <div className="mt-1 text-gray-500">
                                                安全 {number(material.safe_stock)}
                                            </div>
                                        </div>
                                    </ListRow>
                                ))}
                            </Panel>
                        )}
                    </section>

                    <section className="grid gap-6 xl:grid-cols-2">
                        {widgets.financialRecords && (
                            <Panel
                                title="未收款"
                                href={route('financial-records.index')}
                                empty="目前沒有未收款"
                            >
                                {unpaidRecords.map((record) => (
                                    <FinancialRow
                                        key={record.id}
                                        record={record}
                                        labels={labels}
                                    />
                                ))}
                            </Panel>
                        )}

                        {widgets.projects && (
                            <Panel
                                title="案件狀態"
                                href={route('projects.index')}
                                empty="目前沒有案件"
                            >
                                <div className="space-y-3">
                                    {projectStatusCounts.map((item) => (
                                        <div
                                            key={item.status}
                                            className="flex items-center justify-between gap-4"
                                        >
                                            <Link
                                                href={route('projects.index', {
                                                    status: item.status,
                                                })}
                                                className="text-sm font-medium text-gray-800 hover:text-indigo-700"
                                            >
                                                {item.label}
                                            </Link>
                                            <div className="flex min-w-28 items-center gap-3">
                                                <div className="h-2 flex-1 rounded-full bg-gray-100">
                                                    <div
                                                        className="h-2 rounded-full bg-indigo-600"
                                                        style={{
                                                            width: `${statusWidth(
                                                                item.total,
                                                                projectStatusCounts,
                                                            )}%`,
                                                        }}
                                                    />
                                                </div>
                                                <span className="w-8 text-right text-sm text-gray-600">
                                                    {item.total}
                                                </span>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </Panel>
                        )}
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Metric({ label, value, href, tone = 'default' }) {
    const toneClass =
        tone === 'danger'
            ? 'border-red-200 bg-red-50 text-red-900'
            : tone === 'warning'
              ? 'border-amber-200 bg-amber-50 text-amber-900'
              : 'border-gray-200 bg-white text-gray-950';

    return (
        <Link
            href={href}
            className={`border p-5 shadow-sm transition hover:border-indigo-300 sm:rounded-lg ${toneClass}`}
        >
            <div className="text-sm font-medium opacity-75">{label}</div>
            <div className="mt-3 text-2xl font-semibold">{value}</div>
        </Link>
    );
}

function Panel({ title, href, empty, children, danger = false, warning = false }) {
    const hasChildren = Array.isArray(children) ? children.length > 0 : Boolean(children);
    const accent = danger ? 'text-red-700' : warning ? 'text-amber-700' : 'text-gray-950';

    return (
        <section className="bg-white shadow-sm sm:rounded-lg">
            <div className="flex items-center justify-between gap-4 border-b border-gray-100 px-5 py-4">
                <h3 className={`text-base font-semibold ${accent}`}>{title}</h3>
                <Link href={href} className="text-sm font-medium text-indigo-700 hover:text-indigo-900">
                    查看
                </Link>
            </div>
            <div className="divide-y divide-gray-100 p-5">
                {hasChildren ? children : <p className="text-sm text-gray-500">{empty}</p>}
            </div>
        </section>
    );
}

function ListRow({ children }) {
    return <div className="flex items-start justify-between gap-4 py-3 first:pt-0 last:pb-0">{children}</div>;
}

function FinancialRow({ record, labels }) {
    return (
        <ListRow>
            <div>
                <Link
                    href={route('financial-records.show', record.id)}
                    className="font-medium text-gray-950 hover:text-indigo-700"
                >
                    {record.title}
                </Link>
                <div className="mt-1 text-sm text-gray-500">
                    {record.project.project_no} · {record.project.name}
                </div>
                <div className="mt-1 text-sm text-gray-500">
                    {labels.financialTypes[record.type] ?? record.type} · 應收{' '}
                    {record.due_date || '未填'}
                </div>
            </div>
            <div className="text-right text-sm">
                <div className={record.is_overdue ? 'font-semibold text-red-700' : 'font-semibold text-gray-950'}>
                    {money(record.amount)}
                </div>
                <div className="mt-1 text-gray-500">
                    {labels.financialStatuses[record.status] ?? record.status}
                </div>
            </div>
        </ListRow>
    );
}

function statusWidth(total, items) {
    const max = Math.max(...items.map((item) => item.total), 1);
    return Math.max(8, Math.round((total / max) * 100));
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
