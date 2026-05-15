import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Link, router, useForm, usePage } from '@inertiajs/react';

const emptyProgressLog = {
    project_id: '',
    dispatch_id: '',
    worker_id: '',
    work_date: new Date().toISOString().slice(0, 10),
    weather: '',
    worker_count: 0,
    progress_percent: 0,
    work_items: '',
    description: '',
    issue: '',
    voice_text: '',
    latitude: '',
    longitude: '',
    note: '',
    photos: [],
};

export default function ProgressLogForm({
    progressLog = null,
    options,
    submitLabel,
}) {
    const { features = {} } = usePage().props;
    const { can } = useAuthorization();
    const progressPhotosEnabled = Boolean(features.progressPhotos);
    const hasExistingPhotos = (progressLog?.photos?.length ?? 0) > 0;
    const canDeletePhotos =
        progressPhotosEnabled && can(CAPABILITIES.progressLogs.update);
    const { data, setData, post, processing, errors, transform } = useForm({
        ...emptyProgressLog,
        ...progressLog,
        photos: [],
    });

    const submit = (event) => {
        event.preventDefault();

        if (progressLog?.id) {
            transform((values) => ({ ...values, _method: 'patch' }));
            post(route('progress-logs.update', progressLog.id), {
                forceFormData: true,
            });
            return;
        }

        post(route('progress-logs.store'), { forceFormData: true });
    };

    const selectDispatch = (dispatchId) => {
        const dispatch = options.dispatches.find(
            (item) => String(item.id) === String(dispatchId),
        );

        setData({
            ...data,
            dispatch_id: dispatchId,
            project_id: dispatch?.project_id ?? data.project_id,
            work_items: data.work_items || dispatch?.work_item || '',
        });
    };

    const deletePhoto = (photo) => {
        if (!window.confirm('確定要刪除此工地照片嗎？')) {
            return;
        }

        router.delete(route('progress-photos.destroy', photo.id), {
            preserveScroll: true,
        });
    };

    const dispatches = data.project_id
        ? options.dispatches.filter(
              (dispatch) =>
                  String(dispatch.project_id) === String(data.project_id),
          )
        : options.dispatches;

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    日誌基本資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="project_id"
                            value="工程案件"
                            required
                        />
                        <select
                            id="project_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.project_id ?? ''}
                            onChange={(event) =>
                                setData('project_id', event.target.value)
                            }
                            required
                        >
                            <option value="">請選擇案件</option>
                            {options.projects.map((project) => (
                                <option key={project.id} value={project.id}>
                                    {project.project_no} · {project.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.project_id}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="dispatch_id" value="關聯派工" />
                        <select
                            id="dispatch_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.dispatch_id ?? ''}
                            onChange={(event) =>
                                selectDispatch(event.target.value)
                            }
                        >
                            <option value="">不指定派工</option>
                            {dispatches.map((dispatch) => (
                                <option key={dispatch.id} value={dispatch.id}>
                                    {dispatch.scheduled_date} ·{' '}
                                    {dispatch.work_item}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.dispatch_id}
                            className="mt-2"
                        />
                    </div>

                    <Field
                        id="work_date"
                        type="date"
                        label="施工日期"
                        value={data.work_date}
                        onChange={(value) => setData('work_date', value)}
                        error={errors.work_date}
                        required
                    />

                    <div>
                        <InputLabel htmlFor="worker_id" value="回報師傅" />
                        <select
                            id="worker_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.worker_id ?? ''}
                            onChange={(event) =>
                                setData('worker_id', event.target.value)
                            }
                        >
                            <option value="">不指定師傅</option>
                            {options.workers.map((worker) => (
                                <option key={worker.id} value={worker.id}>
                                    {worker.name}
                                    {worker.role ? ` · ${worker.role}` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.worker_id}
                            className="mt-2"
                        />
                    </div>

                    <Field
                        id="weather"
                        label="天氣"
                        value={data.weather ?? ''}
                        onChange={(value) => setData('weather', value)}
                        error={errors.weather}
                        placeholder="晴、陰、雨..."
                    />

                    <Field
                        id="worker_count"
                        type="number"
                        label="施工人數"
                        value={data.worker_count ?? 0}
                        onChange={(value) => setData('worker_count', value)}
                        error={errors.worker_count}
                        min="0"
                    />

                    <Field
                        id="progress_percent"
                        type="number"
                        label="完成進度 (%)"
                        value={data.progress_percent ?? 0}
                        onChange={(value) =>
                            setData('progress_percent', value)
                        }
                        error={errors.progress_percent}
                        min="0"
                        max="100"
                        required
                    />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            id="latitude"
                            type="number"
                            step="0.0000001"
                            label="GPS 緯度"
                            value={data.latitude ?? ''}
                            onChange={(value) => setData('latitude', value)}
                            error={errors.latitude}
                        />
                        <Field
                            id="longitude"
                            type="number"
                            step="0.0000001"
                            label="GPS 經度"
                            value={data.longitude ?? ''}
                            onChange={(value) => setData('longitude', value)}
                            error={errors.longitude}
                        />
                    </div>
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    現場內容
                </h3>

                <TextArea
                    id="work_items"
                    label="今日工項"
                    value={data.work_items ?? ''}
                    onChange={(value) => setData('work_items', value)}
                    error={errors.work_items}
                    placeholder="今日施作項目、區域、樓層或材料..."
                />
                <TextArea
                    id="description"
                    label="進度回報"
                    value={data.description ?? ''}
                    onChange={(value) => setData('description', value)}
                    error={errors.description}
                />
                <TextArea
                    id="issue"
                    label="異常狀況"
                    value={data.issue ?? ''}
                    onChange={(value) => setData('issue', value)}
                    error={errors.issue}
                />
                <TextArea
                    id="voice_text"
                    label="語音轉文字"
                    value={data.voice_text ?? ''}
                    onChange={(value) => setData('voice_text', value)}
                    error={errors.voice_text}
                />
                <TextArea
                    id="note"
                    label="備註"
                    value={data.note ?? ''}
                    onChange={(value) => setData('note', value)}
                    error={errors.note}
                />
            </section>

            {(progressPhotosEnabled || hasExistingPhotos) && (
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    工地照片
                </h3>

                {progressPhotosEnabled && (
                <div>
                    <InputLabel htmlFor="photos" value="新增照片" />
                    <input
                        id="photos"
                        type="file"
                        multiple
                        accept="image/*"
                        className="mt-1 block w-full text-sm text-gray-700 file:mr-4 file:rounded-md file:border-0 file:bg-indigo-50 file:px-4 file:py-2 file:text-sm file:font-medium file:text-indigo-700 hover:file:bg-indigo-100"
                        onChange={(event) =>
                            setData('photos', Array.from(event.target.files))
                        }
                    />
                    <InputError message={errors.photos} className="mt-2" />
                    <InputError message={errors['photos.0']} className="mt-2" />
                </div>
                )}

                {progressLog?.photos?.length > 0 && (
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {progressLog.photos.map((photo) => (
                            <div
                                key={photo.id}
                                className="overflow-hidden rounded-lg border border-gray-200 bg-white"
                            >
                                <img
                                    src={photo.url}
                                    alt={photo.original_name || '工地照片'}
                                    className="h-40 w-full object-cover"
                                />
                                <div className="space-y-3 p-3">
                                    <p className="text-xs text-gray-500">
                                        {photo.watermark_text || '尚無浮水印資料'}
                                    </p>
                                    {canDeletePhotos && (
                                        <DangerButton
                                            type="button"
                                            onClick={() => deletePhoto(photo)}
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

            <div className="flex items-center justify-end gap-3">
                <Link href={route('progress-logs.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}

function Field({
    id,
    label,
    value,
    onChange,
    error,
    required = false,
    ...props
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput
                id={id}
                className="mt-1 block w-full"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                {...props}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function TextArea({ id, label, value, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <textarea
                id={id}
                rows="4"
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
