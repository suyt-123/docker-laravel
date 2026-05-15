import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyCrew = {
    name: '',
    leader_name: '',
    phone: '',
    specialties_text: '',
    daily_rate: '',
    note: '',
};

export default function WorkCrewForm({ workCrew = null, submitLabel }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyCrew,
        ...workCrew,
    });

    const submit = (event) => {
        event.preventDefault();

        if (workCrew?.id) {
            patch(route('work-crews.update', workCrew.id));
            return;
        }

        post(route('work-crews.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-5 md:grid-cols-2">
                <Field
                    id="name"
                    label="工班名稱"
                    value={data.name}
                    onChange={(value) => setData('name', value)}
                    error={errors.name}
                    required
                />
                <Field
                    id="leader_name"
                    label="負責人"
                    value={data.leader_name ?? ''}
                    onChange={(value) => setData('leader_name', value)}
                    error={errors.leader_name}
                />
                <Field
                    id="phone"
                    label="電話"
                    value={data.phone ?? ''}
                    onChange={(value) => setData('phone', value)}
                    error={errors.phone}
                />
                <Field
                    id="daily_rate"
                    type="number"
                    label="預設日薪"
                    value={data.daily_rate ?? ''}
                    onChange={(value) => setData('daily_rate', value)}
                    error={errors.daily_rate}
                    min="0"
                />
            </div>

            <div>
                <InputLabel htmlFor="specialties_text" value="擅長工項" />
                <textarea
                    id="specialties_text"
                    className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    value={data.specialties_text ?? ''}
                    onChange={(event) =>
                        setData('specialties_text', event.target.value)
                    }
                    placeholder="每行一個，例如：H 鋼、浪板、採光罩"
                />
                <InputError message={errors.specialties_text} className="mt-2" />
            </div>

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
                <Link href={route('work-crews.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}

function Field({ id, label, value, onChange, error, type = 'text', required = false, ...props }) {
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
