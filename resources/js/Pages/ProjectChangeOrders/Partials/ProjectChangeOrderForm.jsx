import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyOrder = {
    project_id: '',
    quotation_id: '',
    title: '',
    description: '',
    amount: 0,
    requires_formal_quotation: false,
    requested_date: '',
    approved_date: '',
    due_date: '',
    status: 'pending',
    customer_note: '',
    internal_note: '',
};

export default function ProjectChangeOrderForm({
    order = null,
    options,
    statuses,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyOrder,
        ...order,
    });

    const submit = (event) => {
        event.preventDefault();

        if (order?.id) {
            patch(route('project-change-orders.update', order.id));
            return;
        }

        post(route('project-change-orders.store'));
    };

    const selectedProject = options.projects.find(
        (project) => String(project.id) === String(data.project_id),
    );

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-5 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="project_id" value="工程案件" required />
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
                            客戶：{selectedProject.customer?.name || '未填'}
                        </p>
                    )}
                </div>

                <Field
                    id="title"
                    label="追加項目"
                    value={data.title}
                    onChange={(value) => setData('title', value)}
                    error={errors.title}
                    required
                />

                <Field
                    id="amount"
                    type="number"
                    label="追加金額"
                    value={data.amount}
                    onChange={(value) => setData('amount', value)}
                    error={errors.amount}
                    min="0"
                    required
                />

                <div className="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3">
                    <input
                        id="requires_formal_quotation"
                        type="checkbox"
                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        checked={Boolean(data.requires_formal_quotation)}
                        onChange={(event) =>
                            setData(
                                'requires_formal_quotation',
                                event.target.checked,
                            )
                        }
                    />
                    <InputLabel
                        htmlFor="requires_formal_quotation"
                        value="需要正式追加報價單"
                    />
                    <InputError
                        message={errors.requires_formal_quotation}
                        className="mt-2"
                    />
                </div>

                {data.requires_formal_quotation && (
                    <div>
                        <InputLabel htmlFor="quotation_id" value="既有追加報價單" />
                        <select
                            id="quotation_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.quotation_id ?? ''}
                            onChange={(event) =>
                                setData('quotation_id', event.target.value)
                            }
                        >
                            <option value="">尚未建立或不指定</option>
                            {options.quotations.map((quotation) => (
                                <option key={quotation.id} value={quotation.id}>
                                    {quotation.quotation_no} ·{' '}
                                    {quotation.customer?.name || '未填客戶'} ·{' '}
                                    {quotation.status} · NT${' '}
                                    {Number(
                                        quotation.total ?? 0,
                                    ).toLocaleString()}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.quotation_id}
                            className="mt-2"
                        />
                    </div>
                )}

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
                    id="requested_date"
                    type="date"
                    label="提出日期"
                    value={data.requested_date ?? ''}
                    onChange={(value) => setData('requested_date', value)}
                    error={errors.requested_date}
                />

                <Field
                    id="approved_date"
                    type="date"
                    label="客戶確認日期"
                    value={data.approved_date ?? ''}
                    onChange={(value) => setData('approved_date', value)}
                    error={errors.approved_date}
                />

                <Field
                    id="due_date"
                    type="date"
                    label="追加款應收日期"
                    value={data.due_date ?? ''}
                    onChange={(value) => setData('due_date', value)}
                    error={errors.due_date}
                />
            </div>

            <Textarea
                id="description"
                label="追加內容"
                value={data.description}
                onChange={(value) => setData('description', value)}
                error={errors.description}
            />

            <Textarea
                id="customer_note"
                label="客戶確認備註"
                value={data.customer_note}
                onChange={(value) => setData('customer_note', value)}
                error={errors.customer_note}
            />

            <Textarea
                id="internal_note"
                label="內部備註"
                value={data.internal_note}
                onChange={(value) => setData('internal_note', value)}
                error={errors.internal_note}
            />

            <div className="flex justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('project-change-orders.index')}>
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

function Textarea({ id, label, value, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <textarea
                id={id}
                className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
