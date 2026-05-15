import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyUser = {
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    email_verified: false,
    roles: [],
};

export default function UserForm({ user = null, roles, submitLabel }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyUser,
        ...user,
        roles: user?.roles ?? [],
    });

    const toggleRole = (roleId) => {
        const id = Number(roleId);

        setData(
            'roles',
            data.roles.includes(id)
                ? data.roles.filter((selectedId) => selectedId !== id)
                : [...data.roles, id],
        );
    };

    const submit = (event) => {
        event.preventDefault();

        if (user?.id) {
            patch(route('users.update', user.id));
            return;
        }

        post(route('users.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    帳號資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="name"
                        label="姓名"
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                    />

                    <Field
                        id="email"
                        type="email"
                        label="Email"
                        value={data.email}
                        onChange={(value) => setData('email', value)}
                        error={errors.email}
                        required
                    />

                    <Field
                        id="password"
                        type="password"
                        label={user?.id ? '重設密碼' : '密碼'}
                        value={data.password}
                        onChange={(value) => setData('password', value)}
                        error={errors.password}
                        placeholder={user?.id ? '留空則不變更' : ''}
                        required={!user?.id}
                    />

                    <Field
                        id="password_confirmation"
                        type="password"
                        label="確認密碼"
                        value={data.password_confirmation}
                        onChange={(value) =>
                            setData('password_confirmation', value)
                        }
                        error={errors.password_confirmation}
                        placeholder={user?.id ? '重設密碼時才需要填寫' : ''}
                        required={!user?.id}
                    />
                </div>

                <label className="flex items-center gap-3">
                    <Checkbox
                        checked={Boolean(data.email_verified)}
                        onChange={(event) =>
                            setData('email_verified', event.target.checked)
                        }
                    />
                    <span className="text-sm text-gray-700">
                        Email 已驗證
                    </span>
                </label>
                <InputError message={errors.email_verified} />
            </section>

            <section className="space-y-5">
                <div>
                    <h3 className="text-base font-semibold text-gray-950">
                        角色權限
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        可複選角色，實際權限會合併所有角色的權限設定。
                    </p>
                </div>

                <div className="grid gap-3 md:grid-cols-2">
                    {roles.map((role) => (
                        <label
                            key={role.id}
                            className="flex gap-3 rounded-md border border-gray-200 p-4"
                        >
                            <Checkbox
                                checked={data.roles.includes(role.id)}
                                onChange={() => toggleRole(role.id)}
                            />
                            <span>
                                <span className="block text-sm font-medium text-gray-950">
                                    {role.name}
                                </span>
                                <span className="mt-1 block text-xs text-gray-500">
                                    {role.code}
                                    {role.description
                                        ? ` · ${role.description}`
                                        : ''}
                                </span>
                            </span>
                        </label>
                    ))}
                </div>
                <InputError message={errors.roles} />
            </section>

            <div className="flex items-center justify-end gap-3">
                <Link href={route('users.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>
                    {submitLabel}
                </PrimaryButton>
            </div>
        </form>
    );
}

function Field({
    id,
    label,
    type = 'text',
    value,
    onChange,
    error,
    required = false,
    placeholder = '',
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput
                id={id}
                type={type}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 block w-full"
                required={required}
                placeholder={placeholder}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}
