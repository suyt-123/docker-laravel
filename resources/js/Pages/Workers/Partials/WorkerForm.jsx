import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyWorker = {
    user_id: '',
    work_crew_id: '',
    name: '',
    phone: '',
    role: '',
    daily_rate: '',
    certifications_text: '',
    insurance_expires_at: '',
    is_active: true,
    note: '',
};

export default function WorkerForm({
    worker = null,
    workCrews,
    users,
    submitLabel,
}) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyWorker,
        ...worker,
    });

    const submit = (event) => {
        event.preventDefault();
        if (worker?.id) {
            patch(route('workers.update', worker.id));
            return;
        }
        post(route('workers.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-6">
            <div className="grid gap-5 md:grid-cols-2">
                <div>
                    <InputLabel htmlFor="user_id" value="綁定登入帳號" />
                    <select
                        id="user_id"
                        className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.user_id ?? ''}
                        onChange={(event) =>
                            setData('user_id', event.target.value)
                        }
                    >
                        <option value="">未綁定帳號</option>
                        {users.map((user) => (
                            <option key={user.id} value={user.id}>
                                {user.name} · {user.email}
                            </option>
                        ))}
                    </select>
                    <InputError message={errors.user_id} className="mt-2" />
                    <p className="mt-1 text-xs text-gray-500">
                        用來讓師傅登入後對應自己的派工、打卡與進度回報。
                    </p>
                </div>
                <div>
                    <InputLabel htmlFor="work_crew_id" value="所屬工班" />
                    <select id="work_crew_id" className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.work_crew_id ?? ''} onChange={(event) => setData('work_crew_id', event.target.value)}>
                        <option value="">未分工班</option>
                        {workCrews.map((crew) => <option key={crew.id} value={crew.id}>{crew.name}</option>)}
                    </select>
                    <InputError message={errors.work_crew_id} className="mt-2" />
                </div>
                <Field id="name" label="師傅姓名" value={data.name} onChange={(value) => setData('name', value)} error={errors.name} required />
                <Field id="phone" label="電話" value={data.phone ?? ''} onChange={(value) => setData('phone', value)} error={errors.phone} />
                <Field id="role" label="職務" value={data.role ?? ''} onChange={(value) => setData('role', value)} error={errors.role} placeholder="焊接、鎖板、吊掛..." />
                <Field id="daily_rate" type="number" label="日薪" value={data.daily_rate ?? ''} onChange={(value) => setData('daily_rate', value)} error={errors.daily_rate} min="0" />
                <Field id="insurance_expires_at" type="date" label="保險到期日" value={data.insurance_expires_at ?? ''} onChange={(value) => setData('insurance_expires_at', value)} error={errors.insurance_expires_at} />
            </div>

            <label className="inline-flex items-center gap-2">
                <input type="checkbox" className="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" checked={Boolean(data.is_active)} onChange={(event) => setData('is_active', event.target.checked)} />
                <span className="text-sm text-gray-700">啟用</span>
            </label>

            <div>
                <InputLabel htmlFor="certifications_text" value="證照 / 訓練" />
                <textarea id="certifications_text" className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.certifications_text ?? ''} onChange={(event) => setData('certifications_text', event.target.value)} placeholder="每行一個，例如：高空作業、焊接證照、職安訓練" />
                <InputError message={errors.certifications_text} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="note" value="備註" />
                <textarea id="note" className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.note ?? ''} onChange={(event) => setData('note', event.target.value)} />
                <InputError message={errors.note} className="mt-2" />
            </div>

            <div className="flex justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('workers.index')}><SecondaryButton type="button">取消</SecondaryButton></Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}

function Field({ id, label, value, onChange, error, type = 'text', required = false, ...props }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput id={id} type={type} className="mt-1 block w-full" value={value} onChange={(event) => onChange(event.target.value)} required={required} {...props} />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
