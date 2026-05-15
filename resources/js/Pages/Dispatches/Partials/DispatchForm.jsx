import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyDispatch = {
    project_id: '',
    work_crew_id: '',
    work_item: '',
    status: 'scheduled',
    scheduled_date: '',
    start_time: '',
    end_time: '',
    address: '',
    instructions: '',
    workers: [],
};

export default function DispatchForm({
    dispatch = null,
    options,
    statuses,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyDispatch,
        ...dispatch,
        workers: dispatch?.workers ?? [],
    });

    const submit = (event) => {
        event.preventDefault();

        if (dispatch?.id) {
            patch(route('dispatches.update', dispatch.id));
            return;
        }

        post(route('dispatches.store'));
    };

    const selectProject = (projectId) => {
        const project = options.projects.find(
            (item) => String(item.id) === String(projectId),
        );
        setData({
            ...data,
            project_id: projectId,
            address: data.address || project?.address || '',
        });
    };

    const selectCrew = (crewId) => {
        setData({
            ...data,
            work_crew_id: crewId,
            workers: data.workers.filter((worker) => {
                const option = options.workers.find(
                    (item) => item.id === worker.id,
                );
                return String(option?.work_crew_id ?? '') === String(crewId);
            }),
        });
    };

    const toggleWorker = (worker) => {
        const exists = data.workers.some((item) => item.id === worker.id);

        if (exists) {
            setData(
                'workers',
                data.workers.filter((item) => item.id !== worker.id),
            );
            return;
        }

        setData('workers', [
            ...data.workers,
            {
                id: worker.id,
                hours: '',
                wage: worker.daily_rate ?? '',
                note: '',
            },
        ]);
    };

    const updateWorker = (workerId, field, value) => {
        setData(
            'workers',
            data.workers.map((worker) =>
                worker.id === workerId ? { ...worker, [field]: value } : worker,
            ),
        );
    };

    const selectableWorkers = data.work_crew_id
        ? options.workers.filter(
              (worker) =>
                  String(worker.work_crew_id ?? '') ===
                  String(data.work_crew_id),
          )
        : options.workers;

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    派工資料
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
                                selectProject(event.target.value)
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
                        <InputLabel htmlFor="work_crew_id" value="工班" />
                        <select
                            id="work_crew_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.work_crew_id ?? ''}
                            onChange={(event) => selectCrew(event.target.value)}
                        >
                            <option value="">未指定工班</option>
                            {options.workCrews.map((crew) => (
                                <option key={crew.id} value={crew.id}>
                                    {crew.name}
                                    {crew.leader_name
                                        ? ` (${crew.leader_name})`
                                        : ''}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.work_crew_id}
                            className="mt-2"
                        />
                    </div>

                    <Field
                        id="work_item"
                        label="工項"
                        value={data.work_item}
                        onChange={(value) => setData('work_item', value)}
                        error={errors.work_item}
                        placeholder="屋頂骨架施工、浪板安裝..."
                        required
                    />

                    <div>
                        <InputLabel htmlFor="status" value="狀態" required />
                        <select
                            id="status"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                            required
                        >
                            {Object.entries(statuses).map(([value, label]) => (
                                <option key={value} value={value}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.status} className="mt-2" />
                    </div>

                    <Field
                        id="scheduled_date"
                        type="date"
                        label="施工日期"
                        value={data.scheduled_date}
                        onChange={(value) => setData('scheduled_date', value)}
                        error={errors.scheduled_date}
                        required
                    />

                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field
                            id="start_time"
                            type="time"
                            label="開始"
                            value={data.start_time ?? ''}
                            onChange={(value) => setData('start_time', value)}
                            error={errors.start_time}
                        />
                        <Field
                            id="end_time"
                            type="time"
                            label="結束"
                            value={data.end_time ?? ''}
                            onChange={(value) => setData('end_time', value)}
                            error={errors.end_time}
                        />
                    </div>

                    <Field
                        id="address"
                        label="施工地址"
                        value={data.address ?? ''}
                        onChange={(value) => setData('address', value)}
                        error={errors.address}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="instructions" value="注意事項" />
                    <textarea
                        id="instructions"
                        className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.instructions ?? ''}
                        onChange={(event) =>
                            setData('instructions', event.target.value)
                        }
                    />
                    <InputError
                        message={errors.instructions}
                        className="mt-2"
                    />
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    師傅安排
                </h3>

                <div className="space-y-3">
                    {selectableWorkers.length === 0 && (
                        <div className="rounded-md bg-gray-50 px-4 py-6 text-sm text-gray-500">
                            目前沒有可選師傅
                        </div>
                    )}

                    {selectableWorkers.map((worker) => {
                        const selected = data.workers.find(
                            (item) => item.id === worker.id,
                        );

                        return (
                            <div
                                key={worker.id}
                                className="grid gap-3 border border-gray-200 p-4 sm:rounded-lg lg:grid-cols-5 lg:items-center"
                            >
                                <label className="flex items-center gap-3 lg:col-span-2">
                                    <input
                                        type="checkbox"
                                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                                        checked={Boolean(selected)}
                                        onChange={() => toggleWorker(worker)}
                                    />
                                    <span>
                                        <span className="block font-medium text-gray-950">
                                            {worker.name}
                                        </span>
                                        <span className="block text-sm text-gray-500">
                                            {worker.work_crew?.name ||
                                                '未分工班'}{' '}
                                            · {worker.role || '未填職務'}
                                        </span>
                                    </span>
                                </label>

                                <TextInput
                                    type="number"
                                    className="w-full"
                                    value={selected?.hours ?? ''}
                                    onChange={(event) =>
                                        updateWorker(
                                            worker.id,
                                            'hours',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="工時"
                                    disabled={!selected}
                                    step="0.25"
                                    min="0"
                                />

                                <TextInput
                                    type="number"
                                    className="w-full"
                                    value={selected?.wage ?? ''}
                                    onChange={(event) =>
                                        updateWorker(
                                            worker.id,
                                            'wage',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="薪資"
                                    disabled={!selected}
                                    min="0"
                                />

                                <TextInput
                                    className="w-full"
                                    value={selected?.note ?? ''}
                                    onChange={(event) =>
                                        updateWorker(
                                            worker.id,
                                            'note',
                                            event.target.value,
                                        )
                                    }
                                    placeholder="備註"
                                    disabled={!selected}
                                />
                            </div>
                        );
                    })}
                </div>
                <InputError message={errors.workers} className="mt-2" />
            </section>

            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('dispatches.index')}>
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
    type = 'text',
    required = false,
    ...props
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput
                id={id}
                type={type}
                className="mt-1 block w-full"
                value={value}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                {...props}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
