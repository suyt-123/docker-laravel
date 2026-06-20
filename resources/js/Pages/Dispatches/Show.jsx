import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import Modal from '@/Components/Modal';
import { useState } from 'react';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Show({ dispatch, statuses, attendanceTypes }) {
    const { flash = {}, settings = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.dispatches.update);
    const canDelete = can(CAPABILITIES.dispatches.delete);
    const canClock = can(CAPABILITIES.attendance.create);
    const [clocking, setClocking] = useState(null);
    const [clockType, setClockType] = useState(null);
    const {
        data: clockData,
        setData: setClockData,
        post: postClock,
        processing: clockProcessing,
        errors: clockErrors,
        reset: resetClock,
        transform: transformClock,
    } = useForm({
        dispatch_id: dispatch.id,
        type: '',
        latitude: '',
        longitude: '',
        photo: null,
        note: '',
    });
    const requirePhoto = Boolean(settings.attendance?.requirePhoto);

    const destroyDispatch = () => {
        if (!window.confirm(`確定要刪除「${dispatch.work_item}」派工嗎？`)) {
            return;
        }

        router.delete(route('dispatches.destroy', dispatch.id));
    };

    const openClock = (type) => {
        setClockType(type);
        setClockData({
            dispatch_id: dispatch.id,
            type,
            latitude: '',
            longitude: '',
            photo: null,
            note: '',
        });
    };

    const closeClock = () => {
        setClockType(null);
        resetClock();
        setClocking(null);
    };

    const clock = () => {
        const type = clockType;

        if (!type) {
            return;
        }

        setClocking(type);

        const submit = (coords = {}) => {
            transformClock((values) => ({
                ...values,
                latitude: coords.latitude ?? '',
                longitude: coords.longitude ?? '',
            }));
            postClock(route('attendance-records.store'), {
                forceFormData: true,
                preserveScroll: true,
                onSuccess: closeClock,
                onFinish: () => setClocking(null),
            });
        };

        if (!navigator.geolocation) {
            submit();
            return;
        }

        navigator.geolocation.getCurrentPosition(
            (position) =>
                submit({
                    latitude: position.coords.latitude,
                    longitude: position.coords.longitude,
                }),
            () => submit(),
            {
                enableHighAccuracy: true,
                timeout: 10000,
                maximumAge: 0,
            },
        );
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {dispatch.work_item}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {dispatch.scheduled_date}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('dispatches.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('dispatches.edit', dispatch.id)}>
                                <PrimaryButton>編輯派工</PrimaryButton>
                            </Link>
                        )}
                        {canClock && (
                            <>
                                <PrimaryButton
                                    type="button"
                                    disabled={clockProcessing}
                                    onClick={() => openClock('clock_in')}
                                >
                                    上工打卡
                                </PrimaryButton>
                                <SecondaryButton
                                    type="button"
                                    disabled={clockProcessing}
                                    onClick={() => openClock('clock_out')}
                                >
                                    下工打卡
                                </SecondaryButton>
                            </>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={dispatch.work_item} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid gap-5 lg:grid-cols-3">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                            <h3 className="text-base font-semibold text-gray-950">
                                派工資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="工程案件"
                                    value={`${dispatch.project.project_no} · ${dispatch.project.name}`}
                                />
                                <Info
                                    label="客戶"
                                    value={dispatch.project.customer?.name}
                                />
                                <Info
                                    label="狀態"
                                    value={statuses[dispatch.status]}
                                />
                                <Info
                                    label="工班"
                                    value={dispatch.work_crew?.name}
                                />
                                <Info
                                    label="時間"
                                    value={`${dispatch.scheduled_date} ${
                                        [dispatch.start_time, dispatch.end_time]
                                            .filter(Boolean)
                                            .join(' - ') || ''
                                    }`}
                                />
                                <Info label="建立人" value={dispatch.creator?.name} />
                                <Info label="施工地址" value={dispatch.address} wide />
                                <Info
                                    label="注意事項"
                                    value={dispatch.instructions}
                                    wide
                                />
                            </dl>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                工班聯絡
                            </h3>
                            <dl className="mt-5 space-y-4">
                                <Info
                                    label="工班"
                                    value={dispatch.work_crew?.name}
                                />
                                <Info
                                    label="負責人"
                                    value={dispatch.work_crew?.leader_name}
                                />
                                <Info
                                    label="電話"
                                    value={dispatch.work_crew?.phone}
                                />
                            </dl>
                        </section>
                    </div>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-base font-semibold text-gray-950">
                                師傅安排
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>師傅</Header>
                                        <Header>職務</Header>
                                        <Header align="right">工時</Header>
                                        <Header align="right">薪資</Header>
                                        <Header>備註</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {dispatch.workers.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="5"
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                尚未安排師傅
                                            </td>
                                        </tr>
                                    )}
                                    {dispatch.workers.map((worker) => (
                                        <tr key={worker.id}>
                                            <td className="px-4 py-4 text-sm font-medium text-gray-950">
                                                {worker.name}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {worker.role || '未填'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {worker.pivot.hours || '未填'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(worker.pivot.wage)}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {worker.pivot.note || '無'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="flex items-center justify-between gap-4 border-b border-gray-200 p-6">
                            <h3 className="text-base font-semibold text-gray-950">
                                GPS 打卡紀錄
                            </h3>
                            <Link
                                href={route('attendance-records.index', {
                                    search: dispatch.project.project_no,
                                })}
                                className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            >
                                查看全部
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>時間</Header>
                                        <Header>類型</Header>
                                        <Header>師傅</Header>
                                        <Header>工時</Header>
                                        <Header>距離</Header>
                                        <Header>狀態</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {dispatch.attendance_records.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="5"
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                尚無打卡紀錄
                                            </td>
                                        </tr>
                                    )}
                                    {dispatch.attendance_records.map((record) => (
                                        <tr key={record.id}>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <Link
                                                    href={route(
                                                        'attendance-records.show',
                                                        record.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {record.recorded_at}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {attendanceTypes[record.type] ??
                                                    record.type}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {record.worker?.name ||
                                                    record.user?.name ||
                                                    '未指定'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {minutes(record.worked_minutes)}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {record.distance_meters === null
                                                    ? '未檢查'
                                                    : `${record.distance_meters} m`}
                                            </td>
                                            <td className="px-4 py-4 text-sm">
                                                {record.requires_attention ? (
                                                    <span className="rounded-full bg-red-50 px-2.5 py-1 text-xs font-medium text-red-700">
                                                        {record.anomaly_reason ||
                                                            '異常'}
                                                    </span>
                                                ) : (
                                                    <span className="rounded-full bg-emerald-50 px-2.5 py-1 text-xs font-medium text-emerald-700">
                                                        正常
                                                    </span>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyDispatch}>
                                刪除派工
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>

            <Modal show={Boolean(clockType)} onClose={closeClock}>
                <form
                    className="space-y-5 p-6"
                    onSubmit={(event) => {
                        event.preventDefault();
                        clock();
                    }}
                >
                    <div>
                        <h3 className="text-lg font-semibold text-gray-950">
                            {attendanceTypes[clockType] ?? '打卡'}
                        </h3>
                        <p className="mt-1 text-sm text-gray-500">
                            送出時會抓取目前 GPS 座標。
                        </p>
                    </div>

                    <div>
                        <label
                            htmlFor="attendance_photo"
                            className="block text-sm font-medium text-gray-700"
                        >
                            打卡照片
                            {requirePhoto && (
                                <span className="ml-1 text-red-600">*</span>
                            )}
                        </label>
                        <input
                            id="attendance_photo"
                            type="file"
                            accept="image/*"
                            capture="environment"
                            className="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                            onChange={(event) =>
                                setClockData(
                                    'photo',
                                    event.target.files?.[0] ?? null,
                                )
                            }
                        />
                        {clockErrors.photo && (
                            <p className="mt-2 text-sm text-red-600">
                                {clockErrors.photo}
                            </p>
                        )}
                    </div>

                    <div>
                        <label
                            htmlFor="attendance_note"
                            className="block text-sm font-medium text-gray-700"
                        >
                            備註
                        </label>
                        <textarea
                            id="attendance_note"
                            rows="3"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={clockData.note}
                            onChange={(event) =>
                                setClockData('note', event.target.value)
                            }
                        />
                        {clockErrors.note && (
                            <p className="mt-2 text-sm text-red-600">
                                {clockErrors.note}
                            </p>
                        )}
                    </div>

                    <div className="flex justify-end gap-3">
                        <SecondaryButton type="button" onClick={closeClock}>
                            取消
                        </SecondaryButton>
                        <PrimaryButton
                            type="submit"
                            disabled={clockProcessing || Boolean(clocking)}
                        >
                            {clocking ? '定位中' : '送出打卡'}
                        </PrimaryButton>
                    </div>
                </form>
            </Modal>
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

function Info({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
        </div>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function minutes(value) {
    if (!value) {
        return '未計算';
    }

    const hours = Math.floor(value / 60);
    const mins = value % 60;

    return `${hours} 小時 ${mins} 分`;
}
