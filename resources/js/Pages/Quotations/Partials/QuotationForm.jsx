import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';
import { useMemo, useState } from 'react';

const emptyItem = {
    material_id: '',
    name: '',
    spec: '',
    unit: '式',
    quantity: 1,
    unit_price: 0,
    cost_price: 0,
    waste_rate: 0,
    note: '',
};

const emptyQuotation = {
    quotation_no: '',
    customer_id: '',
    project_id: '',
    quotation_template_id: '',
    template_inputs: {},
    status: 'draft',
    profit_rate: 0,
    tax: 0,
    discount: 0,
    valid_until: '',
    note: '',
    items: [{ ...emptyItem }],
};

export default function QuotationForm({
    quotation = null,
    options,
    statuses,
    quotationNo = '',
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyQuotation,
        ...quotation,
        quotation_no: quotation?.quotation_no ?? quotationNo,
        items: quotation?.items?.length ? quotation.items : [{ ...emptyItem }],
    });
    const [selectedTemplateId, setSelectedTemplateId] = useState(
        data.quotation_template_id ?? '',
    );
    const selectedTemplate = useMemo(
        () =>
            options.quotationTemplates?.find(
                (template) => String(template.id) === String(selectedTemplateId),
            ),
        [options.quotationTemplates, selectedTemplateId],
    );
    const [templateInputs, setTemplateInputs] = useState(() =>
        defaultTemplateInputs(selectedTemplate),
    );
    const [templateProcessing, setTemplateProcessing] = useState(false);
    const [templateError, setTemplateError] = useState('');

    const submit = (event) => {
        event.preventDefault();

        if (quotation?.id) {
            patch(route('quotations.update', quotation.id));
            return;
        }

        post(route('quotations.store'));
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
            unit_price: material.sale_price ?? 0,
            cost_price: material.cost_price ?? 0,
        };
        setData('items', items);
    };

    const addItem = () => setData('items', [...data.items, { ...emptyItem }]);

    const removeItem = (index) => {
        if (data.items.length === 1) return;
        setData(
            'items',
            data.items.filter((_, itemIndex) => itemIndex !== index),
        );
    };

    const selectTemplate = (templateId) => {
        setSelectedTemplateId(templateId);
        const template = options.quotationTemplates?.find(
            (item) => String(item.id) === String(templateId),
        );
        setTemplateInputs(defaultTemplateInputs(template));
        setTemplateError('');
    };

    const updateTemplateInput = (key, value) => {
        setTemplateInputs((current) => ({ ...current, [key]: value }));
    };

    const applyTemplate = async () => {
        if (!selectedTemplate) return;

        setTemplateProcessing(true);
        setTemplateError('');

        try {
            const response = await window.axios.post(
                route('quotation-templates.calculate', selectedTemplate.id),
                { inputs: templateInputs },
            );
            const payload = response.data;
            setData({
                ...data,
                quotation_template_id: selectedTemplate.id,
                template_inputs: payload.inputs,
                profit_rate: payload.template.profit_rate ?? 0,
                tax: payload.template.tax ?? 0,
                discount: payload.template.discount ?? 0,
                note: data.note || payload.template.note || '',
                items: payload.items.length ? payload.items : data.items,
            });
        } catch (error) {
            setTemplateError(
                error.response?.data?.message || '套用模板失敗，請稍後再試。',
            );
        } finally {
            setTemplateProcessing(false);
        }
    };

    const subtotal = data.items.reduce(
        (sum, item) =>
            sum + Number(item.quantity || 0) * Number(item.unit_price || 0),
        0,
    );
    const total =
        subtotal + Number(data.tax || 0) - Number(data.discount || 0);

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    報價資訊
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="quotation_no"
                        label="報價單號"
                        value={data.quotation_no}
                        onChange={(value) => setData('quotation_no', value)}
                        error={errors.quotation_no}
                        readOnly
                    />

                    <div>
                        <InputLabel value="狀態" required />
                        <div className="mt-1 rounded-md border border-gray-300 bg-gray-100 px-3 py-2 text-sm text-gray-700">
                            {statuses[data.status] ?? data.status}
                        </div>
                        <InputError message={errors.status} className="mt-2" />
                    </div>

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
                        id="profit_rate"
                        type="number"
                        label="利潤率 (%)"
                        value={data.profit_rate ?? 0}
                        onChange={(value) => setData('profit_rate', value)}
                        error={errors.profit_rate}
                        step="0.01"
                        min="0"
                    />

                    <Field
                        id="valid_until"
                        type="date"
                        label="有效期限"
                        value={data.valid_until ?? ''}
                        onChange={(value) => setData('valid_until', value)}
                        error={errors.valid_until}
                    />
                </div>
            </section>

            {!quotation?.id && options.quotationTemplates?.length > 0 && (
                <section className="space-y-5 rounded-md border border-gray-200 bg-gray-50 p-4">
                    <div className="flex flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                        <div className="lg:w-80">
                            <InputLabel
                                htmlFor="quotation_template"
                                value="套用報價模板"
                            />
                            <select
                                id="quotation_template"
                                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                value={selectedTemplateId}
                                onChange={(event) =>
                                    selectTemplate(event.target.value)
                                }
                            >
                                <option value="">不套用模板</option>
                                {options.quotationTemplates.map((template) => (
                                    <option key={template.id} value={template.id}>
                                        {template.name}
                                        {template.type ? ` · ${template.type}` : ''}
                                    </option>
                                ))}
                            </select>
                        </div>
                        {selectedTemplate && (
                            <>
                                <div className="grid flex-1 gap-3 md:grid-cols-3">
                                    {selectedTemplate.parameter_definitions.map(
                                        (parameter) => (
                                            <Field
                                                key={parameter.key}
                                                id={`template_input_${parameter.key}`}
                                                type="number"
                                                label={`${parameter.label}${
                                                    parameter.unit
                                                        ? ` (${parameter.unit})`
                                                        : ''
                                                }`}
                                                value={
                                                    templateInputs[
                                                        parameter.key
                                                    ] ?? ''
                                                }
                                                onChange={(value) =>
                                                    updateTemplateInput(
                                                        parameter.key,
                                                        value,
                                                    )
                                                }
                                                step="0.001"
                                                min="0"
                                            />
                                        ),
                                    )}
                                </div>
                                <SecondaryButton
                                    type="button"
                                    disabled={templateProcessing}
                                    onClick={applyTemplate}
                                >
                                    套用模板
                                </SecondaryButton>
                            </>
                        )}
                    </div>
                    {templateError && (
                        <div className="text-sm text-red-700">
                            {templateError}
                        </div>
                    )}
                </section>
            )}

            <section className="space-y-5">
                <div className="flex items-center justify-between gap-3">
                    <h3 className="text-base font-semibold text-gray-950">
                        報價明細
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
                                    <InputLabel value="材料品項" />
                                    <select
                                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                        value={item.material_id ?? ''}
                                        onChange={(event) =>
                                            selectMaterial(
                                                index,
                                                event.target.value,
                                            )
                                        }
                                    >
                                        <option value="">自訂項目</option>
                                        {options.materials.map((material) => (
                                            <option
                                                key={material.id}
                                                value={material.id}
                                            >
                                                {material.name}
                                                {material.spec
                                                    ? ` · ${material.spec}`
                                                    : ''}
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
                                <div className="lg:col-span-2">
                                    <InputLabel value="備註" />
                                    <TextInput
                                        className="mt-1 block w-full"
                                        value={item.note ?? ''}
                                        onChange={(event) =>
                                            updateItem(
                                                index,
                                                'note',
                                                event.target.value,
                                            )
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
                                                    Number(
                                                        item.unit_price || 0,
                                                    ),
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
                    <InputError message={errors.items} className="mt-2" />
                </div>
            </section>

            <section className="grid gap-5 md:grid-cols-2">
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
                <Link href={route('quotations.index')}>
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

function defaultTemplateInputs(template) {
    if (!template) {
        return {};
    }

    return Object.fromEntries(
        (template.parameter_definitions ?? []).map((parameter) => [
            parameter.key,
            parameter.default ?? '',
        ]),
    );
}
