import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyRole = {
    name: '',
    code: '',
    description: '',
    capabilities: [],
};

export default function RoleForm({ role = null, capabilities, submitLabel }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyRole,
        ...role,
        capabilities: role?.capabilities ?? [],
    });

    const groupedCapabilities = groupCapabilities(capabilities);

    const toggleCapability = (capabilityId) => {
        const id = Number(capabilityId);

        setData(
            'capabilities',
            data.capabilities.includes(id)
                ? data.capabilities.filter((selectedId) => selectedId !== id)
                : [...data.capabilities, id],
        );
    };

    const submit = (event) => {
        event.preventDefault();

        if (role?.id) {
            patch(route('roles.update', role.id));
            return;
        }

        post(route('roles.store'));
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <h3 className="text-base font-semibold text-gray-950">
                    角色資料
                </h3>

                <div className="grid gap-5 md:grid-cols-2">
                    <Field
                        id="name"
                        label="角色名稱"
                        value={data.name}
                        onChange={(value) => setData('name', value)}
                        error={errors.name}
                        required
                    />

                    <Field
                        id="code"
                        label="角色代碼"
                        value={data.code}
                        onChange={(value) => setData('code', value)}
                        error={errors.code}
                        placeholder="custom_project_manager"
                        required
                        readOnly={Boolean(role?.id)}
                    />
                </div>

                <div>
                    <InputLabel htmlFor="description" value="描述" />
                    <textarea
                        id="description"
                        className="mt-1 block min-h-24 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.description ?? ''}
                        onChange={(event) =>
                            setData('description', event.target.value)
                        }
                    />
                    <InputError message={errors.description} className="mt-2" />
                </div>
            </section>

            <section className="space-y-5">
                <div>
                    <h3 className="text-base font-semibold text-gray-950">
                        Capabilities
                    </h3>
                    <p className="mt-1 text-sm text-gray-500">
                        角色只是 capability 的集合，所有授權判斷都會透過集中式 policy layer。
                    </p>
                </div>

                <div className="space-y-4">
                    {Object.entries(groupedCapabilities).map(
                        ([group, groupCapabilities]) => (
                            <div
                                key={group}
                                className="rounded-md border border-gray-200 p-4"
                            >
                                <h4 className="text-sm font-semibold text-gray-950">
                                    {group}
                                </h4>
                                <div className="mt-3 grid gap-3 md:grid-cols-2">
                                    {groupCapabilities.map((capability) => (
                                        <label
                                            key={capability.id}
                                            className="flex gap-3 rounded-md bg-gray-50 p-3"
                                        >
                                            <Checkbox
                                                checked={data.capabilities.includes(
                                                    capability.id,
                                                )}
                                                onChange={() =>
                                                    toggleCapability(
                                                        capability.id,
                                                    )
                                                }
                                            />
                                            <span>
                                                <span className="block text-sm font-medium text-gray-950">
                                                    {capability.name}
                                                </span>
                                                <span className="mt-1 block break-all text-xs text-gray-500">
                                                    {capability.code}
                                                </span>
                                            </span>
                                        </label>
                                    ))}
                                </div>
                            </div>
                        ),
                    )}
                </div>
                <InputError message={errors.capabilities} />
            </section>

            <div className="flex items-center justify-end gap-3">
                <Link href={route('roles.index')}>
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
    value,
    onChange,
    error,
    required = false,
    placeholder = '',
    readOnly = false,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput
                id={id}
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                className="mt-1 block w-full"
                required={required}
                placeholder={placeholder}
                readOnly={readOnly}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function groupCapabilities(capabilities) {
    return capabilities.reduce((groups, capability) => {
        const group = capability.group || '其他';
        return {
            ...groups,
            [group]: [...(groups[group] ?? []), capability],
        };
    }, {});
}
