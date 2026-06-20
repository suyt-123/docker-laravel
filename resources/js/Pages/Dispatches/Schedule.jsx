import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Schedule({ days, dispatches, filters, statuses }) {
    const { data, setData, get, processing } = useForm({
        start: filters.start,
        days: filters.days,
    });
    const grouped = dispatches.reduce((carry, dispatch) => {
        carry[dispatch.date] = [...(carry[dispatch.date] || []), dispatch];
        return carry;
    }, {});

    const submit = (event) => {
        event.preventDefault();
        get(route('dispatches.schedule'), {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    排程甘特圖
                </h2>
            }
        >
            <Head title="排程甘特圖" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg sm:flex-row sm:flex-wrap sm:items-center"
                    >
                        <TextInput
                            type="date"
                            value={data.start}
                            onChange={(event) =>
                                setData('start', event.target.value)
                            }
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.days}
                            onChange={(event) =>
                                setData('days', event.target.value)
                            }
                        >
                            <option value="7">7 天</option>
                            <option value="14">14 天</option>
                            <option value="30">30 天</option>
                        </select>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                查詢
                            </PrimaryButton>
                            <Link href={route('dispatches.schedule')}>
                                <SecondaryButton type="button">
                                    清除
                                </SecondaryButton>
                            </Link>
                        </div>
                    </form>

                    <div className="overflow-x-auto bg-white p-4 shadow-sm sm:rounded-lg">
                        <div
                            className="grid min-w-[920px] gap-3"
                            style={{
                                gridTemplateColumns: `repeat(${days.length}, minmax(150px, 1fr))`,
                            }}
                        >
                            {days.map((day) => (
                                <section
                                    key={day.date}
                                    className="min-h-72 rounded-md border border-gray-200 bg-gray-50"
                                >
                                    <div className="border-b border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-800">
                                        {day.label}
                                    </div>
                                    <div className="space-y-2 p-2">
                                        {(grouped[day.date] || []).length ===
                                            0 && (
                                            <div className="rounded border border-dashed border-gray-200 px-3 py-6 text-center text-sm text-gray-400">
                                                無派工
                                            </div>
                                        )}
                                        {(grouped[day.date] || []).map(
                                            (dispatch) => (
                                                <Link
                                                    key={dispatch.id}
                                                    href={route(
                                                        'dispatches.show',
                                                        dispatch.id,
                                                    )}
                                                    className="block rounded-md border border-indigo-100 bg-white p-3 shadow-sm hover:border-indigo-300"
                                                >
                                                    <div className="text-xs text-gray-500">
                                                        {dispatch.start_time ||
                                                            '--:--'}{' '}
                                                        -{' '}
                                                        {dispatch.end_time ||
                                                            '--:--'}
                                                    </div>
                                                    <div className="mt-1 text-sm font-semibold text-gray-950">
                                                        {dispatch.work_item}
                                                    </div>
                                                    <div className="mt-1 text-xs text-gray-600">
                                                        {
                                                            dispatch.project
                                                                ?.project_no
                                                        }{' '}
                                                        ·{' '}
                                                        {
                                                            dispatch.project
                                                                ?.name
                                                        }
                                                    </div>
                                                    <div className="mt-2 flex flex-wrap gap-1">
                                                        <Badge>
                                                            {dispatch.work_crew
                                                                ?.name ||
                                                                '未分配工班'}
                                                        </Badge>
                                                        <Badge muted>
                                                            {statuses[
                                                                dispatch.status
                                                            ] ??
                                                                dispatch.status}
                                                        </Badge>
                                                    </div>
                                                    <div className="mt-2 text-xs text-gray-500">
                                                        {dispatch.workers
                                                            .map(
                                                                (worker) =>
                                                                    worker.name,
                                                            )
                                                            .join('、') ||
                                                            '未指派師傅'}
                                                    </div>
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                </section>
                            ))}
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Badge({ children, muted = false }) {
    return (
        <span
            className={`inline-flex rounded-full px-2 py-0.5 text-xs font-medium ${
                muted
                    ? 'bg-gray-100 text-gray-600'
                    : 'bg-indigo-50 text-indigo-700'
            }`}
        >
            {children}
        </span>
    );
}
