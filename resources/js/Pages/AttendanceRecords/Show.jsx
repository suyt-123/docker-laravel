import DangerButton from '@/Components/DangerButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router } from '@inertiajs/react';

export default function Show({ attendanceRecord, types }) {
    const { can } = useAuthorization();
    const canDelete = can(CAPABILITIES.attendance.delete);

    const destroyRecord = () => {
        if (!window.confirm('確定要刪除此打卡紀錄嗎？')) {
            return;
        }

        router.delete(route('attendance-records.destroy', attendanceRecord.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            GPS 打卡詳情
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {attendanceRecord.recorded_at}
                        </p>
                    </div>
                    <Link href={route('attendance-records.index')}>
                        <SecondaryButton type="button">返回列表</SecondaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="GPS 打卡詳情" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <div className="grid gap-5 lg:grid-cols-3">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                            <h3 className="text-base font-semibold text-gray-950">
                                打卡資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="類型"
                                    value={
                                        types[attendanceRecord.type] ??
                                        attendanceRecord.type
                                    }
                                />
                                <Info
                                    label="師傅"
                                    value={
                                        attendanceRecord.worker?.name ||
                                        attendanceRecord.user?.name
                                    }
                                />
                                <Info
                                    label="工程案件"
                                    value={`${attendanceRecord.project?.project_no ?? ''} ${attendanceRecord.project?.name ?? ''}`}
                                />
                                <Info
                                    label="派工"
                                    value={attendanceRecord.dispatch?.work_item}
                                />
                                <Info
                                    label="GPS"
                                    value={[
                                        attendanceRecord.latitude,
                                        attendanceRecord.longitude,
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                />
                                <Info
                                    label="距離"
                                    value={
                                        attendanceRecord.distance_meters === null
                                            ? '未檢查'
                                            : `${attendanceRecord.distance_meters} m`
                                    }
                                />
                                <Info
                                    label="本次工時"
                                    value={minutes(
                                        attendanceRecord.worked_minutes,
                                    )}
                                />
                                <Info
                                    label="狀態"
                                    value={
                                        attendanceRecord.requires_attention
                                            ? attendanceRecord.anomaly_reason
                                            : '正常'
                                    }
                                    wide
                                />
                                <Info
                                    label="備註"
                                    value={attendanceRecord.note}
                                    wide
                                />
                            </dl>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                打卡照片
                            </h3>
                            {attendanceRecord.photo_url ? (
                                <a
                                    href={attendanceRecord.photo_url}
                                    target="_blank"
                                    rel="noreferrer"
                                >
                                    <img
                                        src={attendanceRecord.photo_url}
                                        alt="打卡照片"
                                        className="mt-5 aspect-video w-full rounded-lg object-cover"
                                    />
                                </a>
                            ) : (
                                <p className="mt-5 text-sm text-gray-500">
                                    未上傳照片
                                </p>
                            )}
                        </section>
                    </div>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyRecord}>
                                刪除打卡紀錄
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
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

function minutes(value) {
    if (!value) {
        return '未計算';
    }

    const hours = Math.floor(value / 60);
    const mins = value % 60;

    return `${hours} 小時 ${mins} 分`;
}
