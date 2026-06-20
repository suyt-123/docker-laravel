import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ progressLog }) {
    const { features = {}, flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const progressPhotosEnabled = Boolean(features.progressPhotos);
    const hasPhotos = progressLog.photos.length > 0;
    const canUpdate = can(CAPABILITIES.progressLogs.update);
    const canDelete = can(CAPABILITIES.progressLogs.delete);

    const destroyLog = () => {
        if (!window.confirm(`確定要刪除 ${progressLog.work_date} 的工程日誌嗎？`)) {
            return;
        }

        router.delete(route('progress-logs.destroy', progressLog.id));
    };

    const deletePhoto = (photo) => {
        if (!window.confirm('確定要刪除此工地照片嗎？')) {
            return;
        }

        router.delete(route('progress-photos.destroy', photo.id), {
            preserveScroll: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            工程日誌
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {progressLog.work_date} ·{' '}
                            {progressLog.project?.project_no}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('progress-logs.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link
                                href={route(
                                    'progress-logs.edit',
                                    progressLog.id,
                                )}
                            >
                                <PrimaryButton>編輯日誌</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={`工程日誌 ${progressLog.work_date}`} />

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
                                現場回報
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="工程案件"
                                    value={`${progressLog.project?.project_no ?? ''} ${progressLog.project?.name ?? ''}`}
                                />
                                <Info
                                    label="派工"
                                    value={progressLog.dispatch?.work_item}
                                />
                                <Info
                                    label="回報師傅"
                                    value={
                                        progressLog.worker?.name ||
                                        progressLog.creator?.name
                                    }
                                />
                                <Info
                                    label="天氣 / 人數"
                                    value={`${progressLog.weather || '未填'} / ${progressLog.worker_count ?? 0} 人`}
                                />
                                <Info
                                    label="完成進度"
                                    value={`${progressLog.progress_percent}%`}
                                />
                                <Info
                                    label="GPS"
                                    value={[
                                        progressLog.latitude,
                                        progressLog.longitude,
                                    ]
                                        .filter(Boolean)
                                        .join(', ')}
                                />
                                <Info
                                    label="今日工項"
                                    value={progressLog.work_items}
                                    wide
                                />
                                <Info
                                    label="進度回報"
                                    value={progressLog.description}
                                    wide
                                />
                                <Info
                                    label="異常狀況"
                                    value={progressLog.issue}
                                    wide
                                />
                                <Info
                                    label="語音轉文字"
                                    value={progressLog.voice_text}
                                    wide
                                />
                                <Info label="備註" value={progressLog.note} wide />
                            </dl>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                日誌摘要
                            </h3>
                            <div className="mt-5">
                                <div className="h-3 rounded-full bg-gray-100">
                                    <div
                                        className="h-3 rounded-full bg-indigo-600"
                                        style={{
                                            width: `${progressLog.progress_percent}%`,
                                        }}
                                    />
                                </div>
                                <div className="mt-3 text-3xl font-semibold text-gray-950">
                                    {progressLog.progress_percent}%
                                </div>
                                {(progressPhotosEnabled || hasPhotos) && (
                                    <p className="mt-2 text-sm text-gray-500">
                                        照片 {progressLog.photos.length} 張
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>

                    {(progressPhotosEnabled || hasPhotos) && (
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            工地照片
                        </h3>
                        {progressLog.photos.length === 0 ? (
                            <p className="mt-5 text-sm text-gray-500">
                                尚無工地照片
                            </p>
                        ) : (
                            <div className="mt-5 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                                {progressLog.photos.map((photo) => (
                                    <div
                                        key={photo.id}
                                        className="overflow-hidden rounded-lg border border-gray-200"
                                    >
                                        <a
                                            href={photo.url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <img
                                                src={photo.url}
                                                alt={
                                                    photo.original_name ||
                                                    '工地照片'
                                                }
                                                className="h-56 w-full object-cover"
                                            />
                                        </a>
                                        <div className="space-y-3 p-4">
                                            <p className="text-sm text-gray-500">
                                                {photo.watermark_text ||
                                                    '尚無浮水印資料'}
                                            </p>
                                            <p className="text-xs text-gray-400">
                                                {photo.uploader?.name || '未知上傳者'} ·{' '}
                                                {photo.taken_at || '未填時間'}
                                            </p>
                                            {progressPhotosEnabled &&
                                                (canUpdate || canDelete) && (
                                                <DangerButton
                                                    onClick={() =>
                                                        deletePhoto(photo)
                                                    }
                                                >
                                                    刪除照片
                                                </DangerButton>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}
                    </section>
                    )}

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyLog}>
                                刪除日誌
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
