import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyParameter = { key: '', label: '', unit: '', default: '' };
const emptyItem = {
    material_id: '',
    name: '',
    spec: '',
    unit: '式',
    unit_price: 0,
    cost_price: 0,
    waste_rate: 0,
    formula_type: 'fixed_quantity',
    formula_params: { quantity: 1 },
    note: '',
    sort_order: 0,
};

const emptyTemplate = {
    name: '',
    type: '',
    status: 'active',
    profit_rate: 0,
    tax: 0,
    discount: 0,
    parameter_definitions: [{ ...emptyParameter }],
    note: '',
    items: [{ ...emptyItem }],
};

export default function QuotationTemplateForm({
    template = null,
    options,
    statuses,
    formulaTypes,
    submitLabel,
}) {
    const { data, setData, post, patch, transform, processing, errors } = useForm({
        ...emptyTemplate,
        ...template,
        parameter_definitions: template?.parameter_definitions?.length
            ? template.parameter_definitions
            : [{ ...emptyParameter }],
        items: template?.items?.length ? template.items : [{ ...emptyItem }],
    });

    const submit = (event) => {
        event.preventDefault();

        const payload = {
            ...data,
            parameter_definitions: data.parameter_definitions.filter(
                (parameter) => parameter.key || parameter.label,
            ),
        };
        transform(() => payload);

        if (template?.id) {
            patch(route('quotation-templates.update', template.id));
            return;
        }

        post(route('quotation-templates.store'));
    };

    const updateParameter = (index, field, value) => {
        const parameters = [...data.parameter_definitions];
        parameters[index] = { ...parameters[index], [field]: value };
        setData('parameter_definitions', parameters);
    };

    const addParameter = () =>
        setData('parameter_definitions', [
            ...data.parameter_definitions,
            { ...emptyParameter },
        ]);

    const removeParameter = (index) =>
        setData(
            'parameter_definitions',
            data.parameter_definitions.filter((_, itemIndex) => itemIndex !== index),
        );

    const updateItem = (index, field, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const updateFormulaParam = (index, field, value) => {
        const items = [...data.items];
        items[index] = {
            ...items[index],
            formula_params: {
                ...(items[index].formula_params ?? {}),
                [field]: value,
            },
        };
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
            unit_price: material.sale_price ?? 0,
            cost_price: material.cost_price ?? 0,
        };
        setData('items', items);
    };

    const addItem = () =>
        setData('items', [
            ...data.items,
            { ...emptyItem, sort_order: data.items.length },
        ]);

    const removeItem = (index) => {
        if (data.items.length === 1) return;
        setData('items', data.items.filter((_, itemIndex) => itemIndex !== index));
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    模板資訊
                </h3>
                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="name"
                        label="模板名稱"
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                    />
                    <Field
                        id="type"
                        label="模板類型"
                        value={data.type ?? ''}
                        onChange={(value) => setData('type', value)}
                        error={errors.type}
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
                        id="profit_rate"
                        type="number"
                        label="預設利潤率 (%)"
                        value={data.profit_rate ?? 0}
                        onChange={(value) => setData('profit_rate', value)}
                        error={errors.profit_rate}
                        step="0.01"
                        min="0"
                    />
                    <Field
                        id="tax"
                        type="number"
                        label="預設稅金"
                        value={data.tax ?? 0}
                        onChange={(value) => setData('tax', value)}
                        error={errors.tax}
                        min="0"
                    />
                    <Field
                        id="discount"
                        type="number"
                        label="預設折扣"
                        value={data.discount ?? 0}
                        onChange={(value) => setData('discount', value)}
                        error={errors.discount}
                        min="0"
                    />
                </div>
            </section>

            <section className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-base font-semibold text-gray-950">
                        工程參數
                    </h3>
                    <SecondaryButton type="button" onClick={addParameter}>
                        新增參數
                    </SecondaryButton>
                </div>
                <div className="space-y-3">
                    {data.parameter_definitions.map((parameter, index) => (
                        <div
                            key={index}
                            className="grid gap-3 rounded-md border border-gray-200 p-3 md:grid-cols-5"
                        >
                            <Field
                                id={`parameter_${index}_key`}
                                label="參數代碼"
                                value={parameter.key}
                                onChange={(value) =>
                                    updateParameter(index, 'key', value)
                                }
                                error={errors[`parameter_definitions.${index}.key`]}
                            />
                            <Field
                                id={`parameter_${index}_label`}
                                label="顯示名稱"
                                value={parameter.label}
                                onChange={(value) =>
                                    updateParameter(index, 'label', value)
                                }
                                error={errors[`parameter_definitions.${index}.label`]}
                            />
                            <Field
                                id={`parameter_${index}_unit`}
                                label="單位"
                                value={parameter.unit ?? ''}
                                onChange={(value) =>
                                    updateParameter(index, 'unit', value)
                                }
                                error={errors[`parameter_definitions.${index}.unit`]}
                            />
                            <Field
                                id={`parameter_${index}_default`}
                                type="number"
                                label="預設值"
                                value={parameter.default ?? ''}
                                onChange={(value) =>
                                    updateParameter(index, 'default', value)
                                }
                                error={
                                    errors[`parameter_definitions.${index}.default`]
                                }
                                step="0.001"
                            />
                            <div className="flex items-end">
                                <button
                                    type="button"
                                    onClick={() => removeParameter(index)}
                                    className="pb-2 text-sm font-medium text-red-700 hover:text-red-900"
                                >
                                    刪除
                                </button>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

            <section className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-base font-semibold text-gray-950">
                        模板明細
                    </h3>
                    <SecondaryButton type="button" onClick={addItem}>
                        新增項目
                    </SecondaryButton>
                </div>
                <div className="space-y-4">
                    {data.items.map((item, index) => (
                        <div
                            key={index}
                            className="space-y-4 rounded-md border border-gray-200 p-4"
                        >
                            <div className="grid gap-4 lg:grid-cols-6">
                                <div className="lg:col-span-2">
                                    <InputLabel value="材料品項" />
                                    <select
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={item.material_id ?? ''}
                                        onChange={(event) =>
                                            selectMaterial(index, event.target.value)
                                        }
                                    >
                                        <option value="">自訂項目</option>
                                        {options.materials.map((material) => (
                                            <option key={material.id} value={material.id}>
                                                {material.name}
                                                {material.spec ? ` · ${material.spec}` : ''}
                                            </option>
                                        ))}
                                    </select>
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
                                />
                                <Field
                                    id={`items_${index}_unit_price`}
                                    type="number"
                                    label="單價"
                                    value={item.unit_price}
                                    onChange={(value) =>
                                        updateItem(index, 'unit_price', value)
                                    }
                                    error={errors[`items.${index}.unit_price`]}
                                    min="0"
                                    required
                                />
                            </div>

                            <div className="grid gap-4 lg:grid-cols-6">
                                <Field
                                    id={`items_${index}_cost_price`}
                                    type="number"
                                    label="成本"
                                    value={item.cost_price ?? 0}
                                    onChange={(value) =>
                                        updateItem(index, 'cost_price', value)
                                    }
                                    error={errors[`items.${index}.cost_price`]}
                                    min="0"
                                />
                                <Field
                                    id={`items_${index}_waste_rate`}
                                    type="number"
                                    label="損耗率 (%)"
                                    value={item.waste_rate ?? 0}
                                    onChange={(value) =>
                                        updateItem(index, 'waste_rate', value)
                                    }
                                    error={errors[`items.${index}.waste_rate`]}
                                    step="0.01"
                                    min="0"
                                />
                                <div>
                                    <InputLabel value="公式類型" required />
                                    <select
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={item.formula_type}
                                        onChange={(event) =>
                                            updateItem(
                                                index,
                                                'formula_type',
                                                event.target.value,
                                            )
                                        }
                                        required
                                    >
                                        {Object.entries(formulaTypes).map(
                                            ([value, label]) => (
                                                <option key={value} value={value}>
                                                    {label}
                                                </option>
                                            ),
                                        )}
                                    </select>
                                </div>
                                <FormulaFields
                                    item={item}
                                    index={index}
                                    onChange={updateFormulaParam}
                                />
                                <div className="flex items-end justify-end">
                                    <button
                                        type="button"
                                        onClick={() => removeItem(index)}
                                        className="pb-2 text-sm font-medium text-red-700 hover:text-red-900"
                                    >
                                        刪除
                                    </button>
                                </div>
                            </div>
                        </div>
                    ))}
                </div>
            </section>

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
                <Link href={route('quotation-templates.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}

function FormulaFields({ item, index, onChange }) {
    const params = item.formula_params ?? {};

    if (item.formula_type === 'fixed_quantity') {
        return (
            <Field
                id={`items_${index}_quantity`}
                type="number"
                label="固定數量"
                value={params.quantity ?? 1}
                onChange={(value) => onChange(index, 'quantity', value)}
                step="0.001"
                min="0"
            />
        );
    }

    return (
        <div className="lg:col-span-2">
            <InputLabel value="公式參數" />
            <TextInput
                className="mt-1 block w-full"
                value={formulaHint(item.formula_type)}
                readOnly
            />
        </div>
    );
}

function formulaHint(type) {
    const hints = {
        area_based: '使用 length × width',
        length_based: '使用 length / spacing',
        panel_count: '使用 length × width / panel_effective_width / panel_length',
        perimeter_based: '使用 2 × (length + width) / piece_length',
    };

    return hints[type] ?? '使用內建公式';
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
                onChange={(event) => onChange?.(event.target.value)}
                required={required}
                {...props}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
