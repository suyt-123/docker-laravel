import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function WorkHours({ filters, summary, workers, crews }) {
    const { data, setData, get, processing } = useForm({
        period: filters.period,
        date: filters.date,
    });
    const previewRange = rangeFor(data.period, data.date);

    const submit = (event) => {
        event.preventDefault();
        get(route('reports.work-hours'), {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    工時統計報表
                </h2>
            }
        >
            <Head title="工時統計報表" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:flex-wrap lg:items-center"
                    >
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.period}
                            onChange={(event) =>
                                setData('period', event.target.value)
                            }
                        >
                            <option value="day">日</option>
                            <option value="week">週</option>
                            <option value="month">月</option>
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
                                查詢
                            </PrimaryButton>
                            <Link href={route('reports.work-hours')}>
                                <SecondaryButton type="button">
                                    清除
                                </SecondaryButton>
                            </Link>
                        </div>
                        <div className="text-sm text-gray-500">
                            區間：{previewRange.start} - {previewRange.end}
                            {(previewRange.start !== filters.start ||
                                previewRange.end !== filters.end) && (
                                <span className="ml-2 text-amber-600">
                                    尚未查詢
                                </span>
                            )}
                        </div>
                    </form>

                    <div className="grid gap-4 md:grid-cols-4">
                        <Metric label="總工時" value={`${summary.worked_hours} 小時`} />
                        <Metric label="上工 / 下工" value={`${summary.clock_in_count} / ${summary.clock_out_count}`} />
                        <Metric label="異常打卡" value={summary.anomaly_count} danger />
                        <Metric label="未下工" value={summary.open_clock_in_count} warning />
                    </div>

                    <ReportTable
                        title="師傅工時"
                        empty="目前沒有師傅工時資料"
                        rows={workers}
                        columns={[
                            ['師傅', (row) => row.worker?.name || '未綁定師傅'],
                            ['工班', (row) => row.worker?.work_crew?.name || '未分配'],
                            ['工時', (row) => `${row.worked_hours} 小時`, 'right'],
                            ['上工 / 下工', (row) => `${row.clock_in_count} / ${row.clock_out_count}`, 'right'],
                            ['未下工', (row) => row.open_clock_in_count, 'right'],
                            ['異常', (row) => row.anomaly_count, 'right'],
                        ]}
                    />

                    <ReportTable
                        title="工班累計"
                        empty="目前沒有工班工時資料"
                        rows={crews}
                        columns={[
                            ['工班', (row) => row.work_crew?.name || '未分配工班'],
                            ['師傅數', (row) => row.worker_count, 'right'],
                            ['累計工時', (row) => `${row.worked_hours} 小時`, 'right'],
                            ['未下工', (row) => row.open_clock_in_count, 'right'],
                            ['異常', (row) => row.anomaly_count, 'right'],
                        ]}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function rangeFor(period, value) {
    const date = parseDate(value);

    if (period === 'day') {
        return {
            start: formatDate(date),
            end: formatDate(date),
        };
    }

    if (period === 'month') {
        return {
            start: formatDate(new Date(date.getFullYear(), date.getMonth(), 1)),
            end: formatDate(new Date(date.getFullYear(), date.getMonth() + 1, 0)),
        };
    }

    const day = date.getDay();
    const mondayOffset = day === 0 ? -6 : 1 - day;
    const start = new Date(date);
    start.setDate(date.getDate() + mondayOffset);
    const end = new Date(start);
    end.setDate(start.getDate() + 6);

    return {
        start: formatDate(start),
        end: formatDate(end),
    };
}

function parseDate(value) {
    if (!value) {
        return new Date();
    }

    const [year, month, day] = value.split('-').map(Number);
    return new Date(year, month - 1, day);
}

function formatDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');

    return `${year}-${month}-${day}`;
}

function Metric({ label, value, danger = false, warning = false }) {
    const color = danger
        ? 'border-red-200 bg-red-50 text-red-900'
        : warning
          ? 'border-amber-200 bg-amber-50 text-amber-900'
          : 'border-gray-200 bg-white text-gray-950';

    return (
        <div className={`rounded-lg border p-5 shadow-sm ${color}`}>
            <div className="text-sm font-medium text-gray-500">{label}</div>
            <div className="mt-3 text-2xl font-semibold">{value}</div>
        </div>
    );
}

function ReportTable({ title, rows, columns, empty }) {
    return (
        <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="border-b border-gray-200 p-6">
                <h3 className="text-base font-semibold text-gray-950">
                    {title}
                </h3>
            </div>
            <div className="overflow-x-auto">
                <table className="min-w-full divide-y divide-gray-200">
                    <thead className="bg-gray-50">
                        <tr>
                            {columns.map(([label, , align]) => (
                                <th
                                    key={label}
                                    className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${
                                        align === 'right'
                                            ? 'text-right'
                                            : 'text-left'
                                    }`}
                                >
                                    {label}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-100 bg-white">
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={columns.length}
                                    className="px-4 py-10 text-center text-sm text-gray-500"
                                >
                                    {empty}
                                </td>
                            </tr>
                        )}
                        {rows.map((row, index) => (
                            <tr key={index} className="hover:bg-gray-50">
                                {columns.map(([label, render, align]) => (
                                    <td
                                        key={label}
                                        className={`px-4 py-4 text-sm text-gray-700 ${
                                            align === 'right'
                                                ? 'text-right'
                                                : 'text-left'
                                        }`}
                                    >
                                        {render(row)}
                                    </td>
                                ))}
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </section>
    );
}
