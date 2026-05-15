import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptySupplier = {
    name: '',
    contact_name: '',
    phone: '',
    email: '',
    tax_id: '',
    address: '',
    payment_terms: '',
    is_active: true,
    note: '',
};

export default function SupplierForm({ supplier = null, submitLabel }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptySupplier,
        ...supplier,
    });

    const submit = (event) => {
        event.preventDefault();

        if (supplier?.id) {
            patch(route('suppliers.update', supplier.id));
            return;
        }

        post(route('suppliers.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    id="name"
                    label="供應商名稱"
                    value={data.name}
                    onChange={(value) => setData('name', value)}
                    error={errors.name}
                    required
                />
                <Field
                    id="contact_name"
                    label="聯絡人"
                    value={data.contact_name ?? ''}
                    onChange={(value) => setData('contact_name', value)}
                    error={errors.contact_name}
                />
                <Field
                    id="phone"
                    label="電話"
                    value={data.phone ?? ''}
                    onChange={(value) => setData('phone', value)}
                    error={errors.phone}
                />
                <Field
                    id="email"
                    type="email"
                    label="Email"
                    value={data.email ?? ''}
                    onChange={(value) => setData('email', value)}
                    error={errors.email}
                />
                <Field
                    id="tax_id"
                    label="統編"
                    value={data.tax_id ?? ''}
                    onChange={(value) => setData('tax_id', value)}
                    error={errors.tax_id}
                />
                <Field
                    id="payment_terms"
                    label="付款條件"
                    value={data.payment_terms ?? ''}
                    onChange={(value) => setData('payment_terms', value)}
                    error={errors.payment_terms}
                />
                <label className="flex items-center gap-3 pt-6 text-sm text-gray-700">
                    <input
                        type="checkbox"
                        checked={Boolean(data.is_active)}
                        onChange={(event) =>
                            setData('is_active', event.target.checked)
                        }
                        className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500"
                    />
                    啟用供應商
                </label>
            </div>

            <Textarea
                id="address"
                label="地址"
                value={data.address}
                onChange={(value) => setData('address', value)}
                error={errors.address}
            />

            <Textarea
                id="note"
                label="備註"
                value={data.note}
                onChange={(value) => setData('note', value)}
                error={errors.note}
            />

            <div className="flex justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('suppliers.index')}>
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
                className="mt-1 block min-h-24 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
