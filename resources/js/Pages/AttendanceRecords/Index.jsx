import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ attendanceRecords, filters, types }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canDelete = can(CAPABILITIES.attendance.delete);
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        type: filters.type ?? '',
        date: filters.date ?? '',
        attention: filters.attention ?? false,
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('attendance-records.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyRecord = (record) => {
        if (!window.confirm('確定要刪除此打卡紀錄嗎？')) {
            return;
        }

        router.delete(route('attendance-records.destroy', record.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    GPS 打卡
                </h2>
            }
        >
            <Head title="GPS 打卡" />

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
                            placeholder="搜尋案件、工項、師傅、異常..."
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(event) =>
                                setData('type', event.target.value)
                            }
                        >
                            <option value="">全部類型</option>
                            {Object.entries(types).map(([value, label]) => (
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
                        <label className="inline-flex items-center gap-2 text-sm text-gray-700">
                            <input
                                type="checkbox"
                                className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                checked={Boolean(data.attention)}
                                onChange={(event) =>
                                    setData('attention', event.target.checked)
                                }
                            />
                            只看異常
                        </label>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('attendance-records.index')}>
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
                                        <Header>時間 / 類型</Header>
                                        <Header>案件</Header>
                                        <Header>師傅</Header>
                                        <Header>工時</Header>
                                        <Header>距離</Header>
                                        <Header>狀態</Header>
                                        <Header align="right">操作</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {attendanceRecords.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有打卡紀錄
                                            </td>
                                        </tr>
                                    )}
                                    {attendanceRecords.data.map((record) => (
                                        <tr
                                            key={record.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <Cell>
                                                <Link
                                                    href={route(
                                                        'attendance-records.show',
                                                        record.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {record.recorded_at}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {types[record.type] ??
                                                        record.type}
                                                </div>
                                            </Cell>
                                            <Cell>
                                                <div className="font-medium text-gray-950">
                                                    {record.project?.project_no}
                                                </div>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {record.dispatch?.work_item ||
                                                        record.project?.name}
                                                </div>
                                            </Cell>
                                            <Cell>
                                                {record.worker?.name ||
                                                    record.user?.name ||
                                                    '未指定'}
                                            </Cell>
                                            <Cell>
                                                {minutes(record.worked_minutes)}
                                            </Cell>
                                            <Cell>
                                                {record.distance_meters === null
                                                    ? '未檢查'
                                                    : `${record.distance_meters} m`}
                                            </Cell>
                                            <Cell>
                                                <Status record={record} />
                                            </Cell>
                                            <Cell align="right">
                                                <div className="flex justify-end gap-3">
                                                    <Link
                                                        href={route(
                                                            'attendance-records.show',
                                                            record.id,
                                                        )}
                                                        className="font-medium text-indigo-700 hover:text-indigo-900"
                                                    >
                                                        查看
                                                    </Link>
                                                    {canDelete && (
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                destroyRecord(
                                                                    record,
                                                                )
                                                            }
                                                            className="font-medium text-red-700 hover:text-red-900"
                                                        >
                                                            刪除
                                                        </button>
                                                    )}
                                                </div>
                                            </Cell>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <Pagination links={attendanceRecords.links} />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Status({ record }) {
    if (record.requires_attention) {
        return (
            <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                {record.anomaly_reason || '異常'}
            </span>
        );
    }

    if (record.is_within_range === false) {
        return (
            <span className="rounded-full bg-amber-50 px-2.5 py-1 text-xs font-medium text-amber-700">
                距離偏遠
            </span>
        );
    }

    return (
        <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
            正常
        </span>
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

function minutes(value) {
    if (!value) {
        return '未計算';
    }

    const hours = Math.floor(value / 60);
    const mins = value % 60;

    return `${hours} 小時 ${mins} 分`;
}
