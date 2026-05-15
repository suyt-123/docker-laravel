import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyRecord = {
    project_id: '',
    type: 'deposit',
    title: '',
    amount: 0,
    due_date: '',
    paid_date: '',
    status: 'pending',
    note: '',
};

export default function FinancialRecordForm({
    record = null,
    options,
    types,
    statuses,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyRecord,
        ...record,
    });

    const submit = (event) => {
        event.preventDefault();
        if (record?.id) {
            patch(route('financial-records.update', record.id));
            return;
        }
        post(route('financial-records.store'));
    };

    const selectedProject = options.projects.find(
        (project) => String(project.id) === String(data.project_id),
    );

    return (
        <form onSubmit={submit} className="space-y-6">
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
                    <InputError message={errors.project_id} className="mt-2" />
                    {selectedProject && (
                        <p className="mt-2 text-sm text-gray-500">
                            客戶：{selectedProject.customer?.name || '未填'} ·
                            合約 {money(selectedProject.contract_amount)}
                        </p>
                    )}
                </div>

                <div>
                    <InputLabel htmlFor="type" value="款項類型" required />
                    <select
                        id="type"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.type}
                        onChange={(event) => setData('type', event.target.value)}
                        required
                    >
                        {Object.entries(types).map(([value, label]) => (
                            <option key={value} value={value}>
                                {label}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.type} className="mt-2" />
                </div>

                <Field
                    id="title"
                    label="款項名稱"
                    value={data.title}
                    onChange={(value) => setData('title', value)}
                    error={errors.title}
                    required
                />

                <Field
                    id="amount"
                    type="number"
                    label="金額"
                    value={data.amount}
                    onChange={(value) => setData('amount', value)}
                    error={errors.amount}
                    min="0"
                    required
                />

                <Field
                    id="due_date"
                    type="date"
                    label="應收日期"
                    value={data.due_date ?? ''}
                    onChange={(value) => setData('due_date', value)}
                    error={errors.due_date}
                />

                <Field
                    id="paid_date"
                    type="date"
                    label="實收日期"
                    value={data.paid_date ?? ''}
                    onChange={(value) => setData('paid_date', value)}
                    error={errors.paid_date}
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
            </div>

            <div>
                <InputLabel htmlFor="note" value="備註" />
                <textarea
                    id="note"
                    className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.note ?? ''}
                    onChange={(event) => setData('note', event.target.value)}
                />
                <InputError message={errors.note} className="mt-2" />
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('financial-records.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}

function Field({ id, label, value, onChange, error, type = 'text', required = false, ...props }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput id={id} type={type} className="mt-1 block w-full" value={value} onChange={(event) => onChange(event.target.value)} required={required} {...props} />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
