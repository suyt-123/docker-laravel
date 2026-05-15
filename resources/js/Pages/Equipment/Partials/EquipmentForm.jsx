import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyEquipment = {
    equipment_no: '',
    equipment_category_id: '',
    name: '',
    brand: '',
    model: '',
    serial_no: '',
    asset_tag: '',
    status: 'available',
    condition: 'good',
    current_project_id: '',
    current_worker_id: '',
    current_work_crew_id: '',
    purchase_date: '',
    purchase_price: '',
    warranty_until: '',
    last_maintenance_at: '',
    next_maintenance_at: '',
    note: '',
};

export default function EquipmentForm({
    equipment = null,
    options,
    statuses,
    conditions,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyEquipment,
        ...equipment,
    });

    const submit = (event) => {
        event.preventDefault();

        if (equipment?.id) {
            patch(route('equipment.update', equipment.id));
            return;
        }

        post(route('equipment.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    基本資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="equipment_no"
                        label="機具編號"
                        value={data.equipment_no ?? ''}
                        onChange={(value) => setData('equipment_no', value)}
                        error={errors.equipment_no}
                        placeholder="留空會自動產生"
                        required={Boolean(equipment?.id)}
                    />

                    <div>
                        <InputLabel
                            htmlFor="equipment_category_id"
                            value="機具分類"
                        />
                        <select
                            id="equipment_category_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.equipment_category_id ?? ''}
                            onChange={(event) =>
                                setData(
                                    'equipment_category_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">未分類</option>
                            {options.categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.equipment_category_id}
                            className="mt-2"
                        />
                    </div>

                    <Field
                        id="name"
                        label="機具名稱"
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                    />

                    <Field
                        id="asset_tag"
                        label="資產標籤"
                        value={data.asset_tag ?? ''}
                        onChange={(value) => setData('asset_tag', value)}
                        error={errors.asset_tag}
                    />

                    <Field
                        id="brand"
                        label="品牌"
                        value={data.brand ?? ''}
                        onChange={(value) => setData('brand', value)}
                        error={errors.brand}
                    />

                    <Field
                        id="model"
                        label="型號"
                        value={data.model ?? ''}
                        onChange={(value) => setData('model', value)}
                        error={errors.model}
                    />

                    <Field
                        id="serial_no"
                        label="序號"
                        value={data.serial_no ?? ''}
                        onChange={(value) => setData('serial_no', value)}
                        error={errors.serial_no}
                    />
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    狀態與位置
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <SelectField
                        id="status"
                        label="狀態"
                        value={data.status}
                        onChange={(value) => setData('status', value)}
                        options={statuses}
                        error={errors.status}
                        required
                    />
                    <SelectField
                        id="condition"
                        label="機況"
                        value={data.condition}
                        onChange={(value) => setData('condition', value)}
                        options={conditions}
                        error={errors.condition}
                        required
                    />
                    <OptionSelect
                        id="current_project_id"
                        label="目前工程案件"
                        value={data.current_project_id ?? ''}
                        onChange={(value) => setData('current_project_id', value)}
                        options={options.projects}
                        error={errors.current_project_id}
                        emptyLabel="未配置"
                    />
                    <OptionSelect
                        id="current_work_crew_id"
                        label="目前工班"
                        value={data.current_work_crew_id ?? ''}
                        onChange={(value) =>
                            setData('current_work_crew_id', value)
                        }
                        options={options.workCrews}
                        error={errors.current_work_crew_id}
                        emptyLabel="未配置"
                    />
                    <OptionSelect
                        id="current_worker_id"
                        label="目前借用師傅"
                        value={data.current_worker_id ?? ''}
                        onChange={(value) => setData('current_worker_id', value)}
                        options={options.workers}
                        error={errors.current_worker_id}
                        emptyLabel="未借出"
                    />
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    採購與保養
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="purchase_date"
                        type="date"
                        label="購買日期"
                        value={data.purchase_date ?? ''}
                        onChange={(value) => setData('purchase_date', value)}
                        error={errors.purchase_date}
                    />
                    <Field
                        id="purchase_price"
                        type="number"
                        label="購買金額"
                        value={data.purchase_price ?? ''}
                        onChange={(value) => setData('purchase_price', value)}
                        error={errors.purchase_price}
                        min="0"
                    />
                    <Field
                        id="warranty_until"
                        type="date"
                        label="保固到期日"
                        value={data.warranty_until ?? ''}
                        onChange={(value) => setData('warranty_until', value)}
                        error={errors.warranty_until}
                    />
                    <Field
                        id="last_maintenance_at"
                        type="datetime-local"
                        label="上次保養時間"
                        value={toLocalDateTime(data.last_maintenance_at)}
                        onChange={(value) =>
                            setData('last_maintenance_at', value)
                        }
                        error={errors.last_maintenance_at}
                    />
                    <Field
                        id="next_maintenance_at"
                        type="datetime-local"
                        label="下次保養時間"
                        value={toLocalDateTime(data.next_maintenance_at)}
                        onChange={(value) =>
                            setData('next_maintenance_at', value)
                        }
                        error={errors.next_maintenance_at}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="note" value="備註" />
                    <textarea
                        id="note"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        rows="4"
                        value={data.note ?? ''}
                        onChange={(event) => setData('note', event.target.value)}
                    />
                    <InputError message={errors.note} className="mt-2" />
                </div>
            </section>

            <div className="flex items-center gap-3">
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
                <Link href={route('equipment.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
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
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                {...props}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function SelectField({
    id,
    label,
    value,
    onChange,
    options,
    error,
    required = false,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <select
                id={id}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
            >
                {Object.entries(options).map(([key, label]) => (
                    <option key={key} value={key}>
                        {label}
                    </option>
                ))}
            </select>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function OptionSelect({
    id,
    label,
    value,
    onChange,
    options,
    error,
    emptyLabel,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <select
                id={id}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">{emptyLabel}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.label ?? option.name}
                    </option>
                ))}
            </select>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function toLocalDateTime(value) {
    if (!value) {
        return '';
    }

    return String(value).slice(0, 16);
}
