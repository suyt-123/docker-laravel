import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyItem = {
    material_id: '',
    name: '',
    spec: '',
    unit: '',
    quantity: 1,
    unit_cost: 0,
    note: '',
};

const emptyOrder = {
    purchase_order_no: '',
    supplier_id: '',
    status: 'draft',
    ordered_date: '',
    expected_date: '',
    tax: 0,
    discount: 0,
    note: '',
    items: [{ ...emptyItem }],
};

export default function PurchaseOrderForm({
    order = null,
    options,
    statuses,
    purchaseOrderNo = '',
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyOrder,
        ...order,
        purchase_order_no: order?.purchase_order_no ?? purchaseOrderNo,
        items: order?.items?.length ? order.items : [{ ...emptyItem }],
    });

    const submit = (event) => {
        event.preventDefault();

        if (order?.id) {
            patch(route('purchase-orders.update', order.id));
            return;
        }

        post(route('purchase-orders.store'));
    };

    const updateItem = (index, field, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const selectMaterial = (index, materialId) => {
        const material = options.materials.find(
            (item) => String(item.id) === String(materialId),
        );

        if (!material) {
            updateItem(index, 'material_id', '');
            return;
        }

        const items = [...data.items];
        items[index] = {
            ...items[index],
            material_id: material.id,
            name: material.name,
            spec: material.spec ?? '',
            unit: material.unit,
            unit_cost: material.cost_price ?? 0,
        };
        setData('items', items);
    };

    const addItem = () => setData('items', [...data.items, { ...emptyItem }]);
    const removeItem = (index) => {
        if (data.items.length === 1) return;
        setData('items', data.items.filter((_, itemIndex) => itemIndex !== index));
    };

    const subtotal = data.items.reduce(
        (sum, item) =>
            sum + Number(item.quantity || 0) * Number(item.unit_cost || 0),
        0,
    );
    const total =
        subtotal + Number(data.tax || 0) - Number(data.discount || 0);

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    採購資訊
                </h3>
                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="purchase_order_no"
                        label="採購單號"
                        value={data.purchase_order_no}
                        onChange={(value) => setData('purchase_order_no', value)}
                        error={errors.purchase_order_no}
                        readOnly
                    />
                    <div>
                        <InputLabel htmlFor="supplier_id" value="供應商" required />
                        <select
                            id="supplier_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.supplier_id ?? ''}
                            onChange={(event) =>
                                setData('supplier_id', event.target.value)
                            }
                            required
                        >
                            <option value="">請選擇供應商</option>
                            {options.suppliers.map((supplier) => (
                                <option key={supplier.id} value={supplier.id}>
                                    {supplier.name}
                                    {supplier.phone ? ` (${supplier.phone})` : ''}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.supplier_id} className="mt-2" />
                    </div>
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
                        id="ordered_date"
                        type="date"
                        label="採購日期"
                        value={data.ordered_date ?? ''}
                        onChange={(value) => setData('ordered_date', value)}
                        error={errors.ordered_date}
                    />
                    <Field
                        id="expected_date"
                        type="date"
                        label="預計到貨日"
                        value={data.expected_date ?? ''}
                        onChange={(value) => setData('expected_date', value)}
                        error={errors.expected_date}
                    />
                </div>
            </section>

            <section className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-base font-semibold text-gray-950">
                        採購明細
                    </h3>
                    <SecondaryButton type="button" onClick={addItem}>
                        新增項目
                    </SecondaryButton>
                </div>
                <div className="space-y-4">
                    {data.items.map((item, index) => (
                        <div
                            key={index}
                            className="space-y-4 border border-gray-200 p-4 sm:rounded-lg"
                        >
                            <div className="grid gap-4 lg:grid-cols-6">
                                <div className="lg:col-span-2">
                                    <InputLabel value="材料" required />
                                    <select
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={item.material_id ?? ''}
                                        onChange={(event) =>
                                            selectMaterial(index, event.target.value)
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
                                        message={errors[`items.${index}.material_id`]}
                                        className="mt-2"
                                    />
                                </div>
                                <Field
                                    id={`items_${index}_name`}
                                    label="名稱"
                                    value={item.name}
                                    onChange={(value) =>
                                        updateItem(index, 'name', value)
                                    }
                                    error={errors[`items.${index}.name`]}
                                    required
                                />
                                <Field
                                    id={`items_${index}_spec`}
                                    label="規格"
                                    value={item.spec ?? ''}
                                    onChange={(value) =>
                                        updateItem(index, 'spec', value)
                                    }
                                    error={errors[`items.${index}.spec`]}
                                />
                                <Field
                                    id={`items_${index}_unit`}
                                    label="單位"
                                    value={item.unit}
                                    onChange={(value) =>
                                        updateItem(index, 'unit', value)
                                    }
                                    error={errors[`items.${index}.unit`]}
                                    required
                                    readOnly
                                />
                                <Field
                                    id={`items_${index}_quantity`}
                                    type="number"
                                    label="數量"
                                    value={item.quantity}
                                    onChange={(value) =>
                                        updateItem(index, 'quantity', value)
                                    }
                                    error={errors[`items.${index}.quantity`]}
                                    step="0.001"
                                    min="0"
                                    required
                                />
                            </div>
                            <div className="grid gap-4 lg:grid-cols-6">
                                <Field
                                    id={`items_${index}_unit_cost`}
                                    type="number"
                                    label="單位成本"
                                    value={item.unit_cost}
                                    onChange={(value) =>
                                        updateItem(index, 'unit_cost', value)
                                    }
                                    error={errors[`items.${index}.unit_cost`]}
                                    min="0"
                                    required
                                />
                                <div className="lg:col-span-4">
                                    <InputLabel value="備註" />
                                    <TextInput
                                        className="mt-1 block w-full"
                                        value={item.note ?? ''}
                                        onChange={(event) =>
                                            updateItem(index, 'note', event.target.value)
                                        }
                                    />
                                </div>
                                <div className="flex items-end justify-between gap-3">
                                    <div>
                                        <div className="text-xs font-medium text-gray-500">
                                            小計
                                        </div>
                                        <div className="mt-1 text-sm font-semibold text-gray-950">
                                            {money(
                                                Number(item.quantity || 0) *
                                                    Number(item.unit_cost || 0),
                                            )}
                                        </div>
                                    </div>
                                    <button
                                        type="button"
                                        onClick={() => removeItem(index)}
                                        className="text-sm font-medium text-red-700 hover:text-red-900"
                                    >
                                        刪除
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="grid gap-5 md:grid-cols-2">
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
                <div className="space-y-4 bg-gray-50 p-4 sm:rounded-lg">
                    <Summary label="小計" value={money(subtotal)} />
                    <Field
                        id="tax"
                        type="number"
                        label="稅金"
                        value={data.tax ?? 0}
                        onChange={(value) => setData('tax', value)}
                        error={errors.tax}
                        min="0"
                    />
                    <Field
                        id="discount"
                        type="number"
                        label="折扣"
                        value={data.discount ?? 0}
                        onChange={(value) => setData('discount', value)}
                        error={errors.discount}
                        min="0"
                    />
                    <div className="border-t border-gray-200 pt-4">
                        <Summary label="總額" value={money(total)} strong />
                    </div>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('purchase-orders.index')}>
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

function Summary({ label, value, strong = false }) {
    return (
        <div className="flex items-center justify-between gap-4">
            <div className="text-sm text-gray-600">{label}</div>
            <div
                className={
                    strong
                        ? 'text-lg font-semibold text-gray-950'
                        : 'text-sm font-medium text-gray-950'
                }
            >
                {value}
            </div>
        </div>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
