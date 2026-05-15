import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyTransaction = {
    material_id: '',
    project_id: '',
    type: 'inbound',
    quantity: 1,
    unit: '',
    unit_cost: 0,
    reference_no: '',
    note: '',
    occurred_at: '',
};

export default function InventoryTransactionForm({
    transaction = null,
    options,
    types,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyTransaction,
        ...transaction,
    });

    const submit = (event) => {
        event.preventDefault();

        if (transaction?.id) {
            patch(route('inventory-transactions.update', transaction.id));
            return;
        }

        post(route('inventory-transactions.store'));
    };

    const selectMaterial = (materialId) => {
        const material = options.materials.find(
            (item) => String(item.id) === String(materialId),
        );

        setData({
            ...data,
            material_id: materialId,
            unit: material?.unit ?? '',
            unit_cost: material?.cost_price ?? data.unit_cost,
        });
    };

    const selectedMaterial = options.materials.find(
        (item) => String(item.id) === String(data.material_id),
    );

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    異動資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="material_id" value="材料" required />
                        <select
                            id="material_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.material_id ?? ''}
                            onChange={(event) =>
                                selectMaterial(event.target.value)
                            }
                            required
                        >
                            <option value="">請選擇材料</option>
                            {options.materials.map((material) => (
                                <option key={material.id} value={material.id}>
                                    {material.name}
                                    {material.spec ? ` · ${material.spec}` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.material_id}
                            className="mt-2"
                        />
                        {selectedMaterial && (
                            <p className="mt-2 text-sm text-gray-500">
                                目前庫存 {number(selectedMaterial.current_stock)}{' '}
                                {selectedMaterial.unit}
                            </p>
                        )}
                    </div>

                    <div>
                        <InputLabel htmlFor="type" value="異動類型" required />
                        <select
                            id="type"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(event) =>
                                setData('type', event.target.value)
                            }
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

                    <div>
                        <InputLabel htmlFor="project_id" value="工程案件" />
                        <select
                            id="project_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.project_id ?? ''}
                            onChange={(event) =>
                                setData('project_id', event.target.value)
                            }
                        >
                            <option value="">不綁定案件</option>
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

                    <Field
                        id="occurred_at"
                        type="datetime-local"
                        label="異動時間"
                        value={data.occurred_at ?? ''}
                        onChange={(value) => setData('occurred_at', value)}
                        error={errors.occurred_at}
                    />

                    <Field
                        id="quantity"
                        type="number"
                        label="數量"
                        value={data.quantity}
                        onChange={(value) => setData('quantity', value)}
                        error={errors.quantity}
                        step="0.001"
                        min="0"
                        required
                    />

                    <Field
                        id="unit"
                        label="單位"
                        value={data.unit}
                        onChange={(value) => setData('unit', value)}
                        error={errors.unit}
                        required
                        readOnly
                    />

                    <Field
                        id="unit_cost"
                        type="number"
                        label="單位成本"
                        value={data.unit_cost ?? 0}
                        onChange={(value) => setData('unit_cost', value)}
                        error={errors.unit_cost}
                        min="0"
                    />

                    <Field
                        id="reference_no"
                        label="參考單號"
                        value={data.reference_no ?? ''}
                        onChange={(value) => setData('reference_no', value)}
                        error={errors.reference_no}
                        placeholder="採購單、調撥單、盤點單..."
                    />
                </div>

                <div>
                    <InputLabel htmlFor="note" value="備註" />
                    <textarea
                        id="note"
                        className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.note ?? ''}
                        onChange={(event) =>
                            setData('note', event.target.value)
                        }
                    />
                    <InputError message={errors.note} className="mt-2" />
                </div>
            </section>

            <section className="bg-gray-50 p-4 sm:rounded-lg">
                <div className="flex items-center justify-between gap-4">
                    <span className="text-sm text-gray-600">成本小計</span>
                    <span className="text-lg font-semibold text-gray-950">
                        {money(Number(data.quantity || 0) * Number(data.unit_cost || 0))}
                    </span>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('inventory-transactions.index')}>
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

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
