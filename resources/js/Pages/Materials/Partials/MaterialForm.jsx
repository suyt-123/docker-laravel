import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyMaterial = {
    material_category_id: '',
    category_name: '',
    name: '',
    spec: '',
    unit: '支',
    length: '',
    width: '',
    thickness: '',
    weight: '',
    cost_price: 0,
    sale_price: 0,
    safe_stock: 0,
    current_stock: 0,
};

export default function MaterialForm({
    material = null,
    categories,
    units,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyMaterial,
        ...material,
    });

    const submit = (event) => {
        event.preventDefault();

        if (material?.id) {
            patch(route('materials.update', material.id));
            return;
        }

        post(route('materials.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    基本資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="material_category_id"
                            value="材料分類"
                        />
                        <select
                            id="material_category_id"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.material_category_id ?? ''}
                            onChange={(event) =>
                                setData(
                                    'material_category_id',
                                    event.target.value,
                                )
                            }
                        >
                            <option value="">未分類</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <InputError
                            message={errors.material_category_id}
                            className="mt-2"
                        />
                    </div>

                    <Field
                        id="category_name"
                        label="新增分類名稱"
                        value={data.category_name ?? ''}
                        onChange={(value) => setData('category_name', value)}
                        error={errors.category_name}
                        placeholder="若要建立新分類才填寫"
                    />

                    <Field
                        id="name"
                        label="材料名稱"
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                    />

                    <Field
                        id="spec"
                        label="規格"
                        value={data.spec ?? ''}
                        onChange={(value) => setData('spec', value)}
                        error={errors.spec}
                        placeholder="100x50x20x2.3mm、0.5mm..."
                    />

                    <div>
                        <InputLabel htmlFor="unit" value="單位" required />
                        <select
                            id="unit"
                            className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.unit}
                            onChange={(event) =>
                                setData('unit', event.target.value)
                            }
                            required
                        >
                            {units.map((unit) => (
                                <option key={unit} value={unit}>
                                    {unit}
                                </option>
                            ))}
                        </select>
                        <InputError message={errors.unit} className="mt-2" />
                    </div>
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    尺寸與重量
                </h3>

                <div className="grid gap-5 md:grid-cols-4">
                    <Field
                        id="length"
                        type="number"
                        label="長度"
                        value={data.length ?? ''}
                        onChange={(value) => setData('length', value)}
                        error={errors.length}
                        step="0.001"
                        min="0"
                    />
                    <Field
                        id="width"
                        type="number"
                        label="寬度"
                        value={data.width ?? ''}
                        onChange={(value) => setData('width', value)}
                        error={errors.width}
                        step="0.001"
                        min="0"
                    />
                    <Field
                        id="thickness"
                        type="number"
                        label="厚度"
                        value={data.thickness ?? ''}
                        onChange={(value) => setData('thickness', value)}
                        error={errors.thickness}
                        step="0.001"
                        min="0"
                    />
                    <Field
                        id="weight"
                        type="number"
                        label="重量"
                        value={data.weight ?? ''}
                        onChange={(value) => setData('weight', value)}
                        error={errors.weight}
                        step="0.001"
                        min="0"
                    />
                </div>
            </section>

            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    價格與庫存
                </h3>

                <div className="grid gap-5 md:grid-cols-4">
                    <Field
                        id="cost_price"
                        type="number"
                        label="成本單價"
                        value={data.cost_price ?? 0}
                        onChange={(value) => setData('cost_price', value)}
                        error={errors.cost_price}
                        min="0"
                    />
                    <Field
                        id="sale_price"
                        type="number"
                        label="報價單價"
                        value={data.sale_price ?? 0}
                        onChange={(value) => setData('sale_price', value)}
                        error={errors.sale_price}
                        min="0"
                    />
                    <Field
                        id="safe_stock"
                        type="number"
                        label="安全庫存"
                        value={data.safe_stock ?? 0}
                        onChange={(value) => setData('safe_stock', value)}
                        error={errors.safe_stock}
                        step="0.001"
                        min="0"
                    />
                    <Field
                        id="current_stock"
                        type="number"
                        label="目前庫存"
                        value={data.current_stock ?? 0}
                        onChange={(value) => setData('current_stock', value)}
                        error={errors.current_stock}
                        step="0.001"
                        min="0"
                        readOnly
                    />
                </div>
                <p className="text-sm text-gray-500">
                    目前庫存由庫存異動自動維護，請透過入庫、出庫、退料或盤點調整。
                </p>
            </section>

            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('materials.index')}>
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
