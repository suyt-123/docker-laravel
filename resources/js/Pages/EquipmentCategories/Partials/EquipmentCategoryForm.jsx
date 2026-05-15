import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyCategory = {
    name: '',
    code: '',
    description: '',
    sort_order: 0,
    is_active: true,
};

export default function EquipmentCategoryForm({ category = null, submitLabel }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyCategory,
        ...category,
    });

    const submit = (event) => {
        event.preventDefault();

        if (category?.id) {
            patch(route('equipment-categories.update', category.id));
            return;
        }

        post(route('equipment-categories.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    id="name"
                    label="分類名稱"
                    value={data.name}
                    onChange={(value) => setData('name', value)}
                    error={errors.name}
                    required
                />
                <Field
                    id="code"
                    label="分類代碼"
                    value={data.code}
                    onChange={(value) => setData('code', value)}
                    error={errors.code}
                    required
                    placeholder="power_tools"
                />
                <Field
                    id="sort_order"
                    type="number"
                    label="排序"
                    value={data.sort_order ?? 0}
                    onChange={(value) => setData('sort_order', value)}
                    error={errors.sort_order}
                    min="0"
                />
                <label className="flex items-center gap-3 rounded-md border border-gray-200 px-4 py-3">
                    <input
                        type="checkbox"
                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                        checked={Boolean(data.is_active)}
                        onChange={(event) =>
                            setData('is_active', event.target.checked)
                        }
                    />
                    <span className="text-sm font-medium text-gray-700">
                        啟用
                    </span>
                </label>
            </div>

            <div>
                <InputLabel htmlFor="description" value="說明" />
                <textarea
                    id="description"
                    className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.description ?? ''}
                    onChange={(event) =>
                        setData('description', event.target.value)
                    }
                />
                <InputError message={errors.description} className="mt-2" />
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('equipment-categories.index')}>
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
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
                {...props}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
