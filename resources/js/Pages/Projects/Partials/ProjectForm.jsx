import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useAuthorization } from '@/lib/authorization';
import { Link, useForm } from '@inertiajs/react';

const emptyProject = {
    project_no: '',
    customer_id: '',
    manager_id: '',
    work_crew_id: '',
    name: '',
    type: '',
    status: 'inquiry',
    address: '',
    latitude: '',
    longitude: '',
    start_date: '',
    end_date: '',
    contract_amount: 0,
    estimated_cost: 0,
    actual_cost: 0,
};

export default function ProjectForm({
    project = null,
    options,
    statuses,
    projectNo = '',
    submitLabel,
}) {
    const { canViewProjectFinancials } = useAuthorization();
    const showFinancialFields = canViewProjectFinancials();
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyProject,
        ...project,
        project_no: project?.project_no ?? projectNo,
    });

    const submit = (event) => {
        event.preventDefault();

        if (project?.id) {
            patch(route('projects.update', project.id));
            return;
        }

        post(route('projects.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    案件資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="project_no"
                        label="案件編號"
                        value={data.project_no}
                        onChange={(value) => setData('project_no', value)}
                        error={errors.project_no}
                        readOnly
                    />

                    <Field
                        id="name"
                        label="工程名稱"
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                    />

                    <div>
                        <InputLabel htmlFor="customer_id" value="客戶" required />
                        <select
                            id="customer_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.customer_id ?? ''}
                            onChange={(event) =>
                                setData('customer_id', event.target.value)
                            }
                            required
                        >
                            <option value="">請選擇客戶</option>
                            {options.customers.map((customer) => (
                                <option key={customer.id} value={customer.id}>
                                    {customer.name}
                                    {customer.phone ? ` (${customer.phone})` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.customer_id}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="status"
                            value="案件狀態"
                            required
                        />
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
                        id="type"
                        label="工程類型"
                        value={data.type ?? ''}
                        onChange={(value) => setData('type', value)}
                        error={errors.type}
                        placeholder="鐵皮屋、鋼構、採光罩..."
                    />

                    <div>
                        <InputLabel htmlFor="manager_id" value="負責人" />
                        <select
                            id="manager_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.manager_id ?? ''}
                            onChange={(event) =>
                                setData('manager_id', event.target.value)
                            }
                        >
                            <option value="">未指定</option>
                            {options.managers.map((manager) => (
                                <option key={manager.id} value={manager.id}>
                                    {manager.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.manager_id}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="work_crew_id" value="預定工班" />
                        <select
                            id="work_crew_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.work_crew_id ?? ''}
                            onChange={(event) =>
                                setData('work_crew_id', event.target.value)
                            }
                        >
                            <option value="">未指定</option>
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
                        id="address"
                        label="工程地址"
                        value={data.address ?? ''}
                        onChange={(value) => setData('address', value)}
                        error={errors.address}
                    />
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    日期與定位
                </h3>

                <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-4">
                    <Field
                        id="start_date"
                        type="date"
                        label="開始日期"
                        value={data.start_date ?? ''}
                        onChange={(value) => setData('start_date', value)}
                        error={errors.start_date}
                    />
                    <Field
                        id="end_date"
                        type="date"
                        label="結束日期"
                        value={data.end_date ?? ''}
                        onChange={(value) => setData('end_date', value)}
                        error={errors.end_date}
                    />
                    <Field
                        id="latitude"
                        type="number"
                        label="緯度"
                        value={data.latitude ?? ''}
                        onChange={(value) => setData('latitude', value)}
                        error={errors.latitude}
                        step="0.0000001"
                    />
                    <Field
                        id="longitude"
                        type="number"
                        label="經度"
                        value={data.longitude ?? ''}
                        onChange={(value) => setData('longitude', value)}
                        error={errors.longitude}
                        step="0.0000001"
                    />
                </div>
            </section>

            {showFinancialFields && (
                <section className="space-y-5">
                    <h3 className="text-base font-semibold text-gray-950">
                        金額與成本
                    </h3>

                    <div className="grid gap-5 md:grid-cols-3">
                        <Field
                            id="contract_amount"
                            type="number"
                            label="合約金額"
                            value={data.contract_amount ?? 0}
                            onChange={(value) =>
                                setData('contract_amount', value)
                            }
                            error={errors.contract_amount}
                            min="0"
                        />
                        <Field
                            id="estimated_cost"
                            type="number"
                            label="預估成本"
                            value={data.estimated_cost ?? 0}
                            onChange={(value) =>
                                setData('estimated_cost', value)
                            }
                            error={errors.estimated_cost}
                            min="0"
                        />
                        <Field
                            id="actual_cost"
                            type="number"
                            label="實際成本"
                            value={data.actual_cost ?? 0}
                            onChange={(value) => setData('actual_cost', value)}
                            error={errors.actual_cost}
                            min="0"
                        />
                    </div>
                </section>
            )}

            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('projects.index')}>
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
