import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, useForm, usePage } from '@inertiajs/react';

export default function Edit({ groups }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.systemSettings.update);
    const { data, setData, patch, processing, errors, reset } = useForm({
        settings: initialSettings(groups),
    });

    const submit = (event) => {
        event.preventDefault();

        patch(route('system-settings.update'), {
            preserveScroll: true,
        });
    };

    const updateSetting = (key, value) => {
        setData('settings', setPath(data.settings, key, value));
    };

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        系統設定
                    </h2>
                    <p className="mt-1 text-sm text-gray-500">
                        管理打卡規則、公司資料、報價條款與庫存預設值。
                    </p>
                </div>
            }
        >
            <Head title="系統設定" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form onSubmit={submit} className="space-y-5">
                        {Object.entries(groups).map(([groupName, settings]) => (
                            <section
                                key={groupName}
                                className="bg-white p-6 shadow-sm sm:rounded-lg"
                            >
                                <h3 className="text-base font-semibold text-gray-950">
                                    {groupName}
                                </h3>
                                <div className="mt-5 grid gap-5 md:grid-cols-2">
                                    {settings.map((setting) => (
                                        <SettingField
                                            key={setting.key}
                                            disabled={!canUpdate}
                                            error={
                                                errors[
                                                    `settings.${setting.key}`
                                                ]
                                            }
                                            setting={setting}
                                            value={getPath(
                                                data.settings,
                                                setting.key,
                                            )}
                                            onChange={(value) =>
                                                updateSetting(
                                                    setting.key,
                                                    value,
                                                )
                                            }
                                        />
                                    ))}
                                </div>
                            </section>
                        ))}

                        <div className="flex justify-end gap-2">
                            <SecondaryButton
                                type="button"
                                disabled={processing}
                                onClick={() => reset()}
                            >
                                還原
                            </SecondaryButton>
                            {canUpdate && (
                                <PrimaryButton disabled={processing}>
                                    儲存設定
                                </PrimaryButton>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function SettingField({ setting, value, onChange, error, disabled }) {
    if (setting.type === 'boolean') {
        return (
            <div className="rounded-md border border-gray-200 p-4">
                <label className="flex items-start gap-3">
                    <input
                        type="checkbox"
                        checked={Boolean(value)}
                        disabled={disabled}
                        onChange={(event) => onChange(event.target.checked)}
                        className="mt-1 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 disabled:opacity-50"
                    />
                    <span>
                        <span className="block text-sm font-medium text-gray-900">
                            {setting.label}
                        </span>
                        {setting.description && (
                            <span className="mt-1 block text-sm text-gray-500">
                                {setting.description}
                            </span>
                        )}
                    </span>
                </label>
                <InputError message={error} className="mt-2" />
            </div>
        );
    }

    if (setting.type === 'text') {
        return (
            <div className="md:col-span-2">
                <InputLabel value={setting.label} />
                <textarea
                    value={value ?? ''}
                    disabled={disabled}
                    onChange={(event) => onChange(event.target.value)}
                    rows={4}
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 disabled:bg-gray-100 disabled:text-gray-500"
                />
                {setting.description && (
                    <p className="mt-1 text-sm text-gray-500">
                        {setting.description}
                    </p>
                )}
                <InputError message={error} className="mt-2" />
            </div>
        );
    }

    return (
        <div>
            <InputLabel value={setting.label} />
            <TextInput
                type={setting.type === 'integer' ? 'number' : 'text'}
                min={setting.min ?? undefined}
                value={value ?? ''}
                disabled={disabled}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 block w-full"
            />
            {setting.description && (
                <p className="mt-1 text-sm text-gray-500">
                    {setting.description}
                </p>
            )}
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function initialSettings(groups) {
    return Object.values(groups)
        .flat()
        .reduce(
            (settings, setting) =>
                setPath(settings, setting.key, setting.value),
            {},
        );
}

function getPath(object, path, fallback = '') {
    return path
        .split('.')
        .reduce(
            (value, segment) =>
                value && value[segment] !== undefined
                    ? value[segment]
                    : fallback,
            object,
        );
}

function setPath(object, path, value) {
    const next = { ...object };
    const segments = path.split('.');
    let cursor = next;

    segments.forEach((segment, index) => {
        if (index === segments.length - 1) {
            cursor[segment] = value;
            return;
        }

        cursor[segment] = { ...(cursor[segment] ?? {}) };
        cursor = cursor[segment];
    });

    return next;
}
