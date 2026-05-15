import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ role }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.roles.update);
    const canDelete = can(CAPABILITIES.roles.delete);
    const groupedCapabilities = groupCapabilities(role.capabilities ?? []);

    const destroyRole = () => {
        if (window.confirm(`確定要刪除「${role.name}」嗎？`)) {
            router.delete(route('roles.destroy', role.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {role.name}
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('roles.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('roles.edit', role.id)}>
                                <PrimaryButton>編輯角色</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={role.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            角色資訊
                        </h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info label="角色名稱" value={role.name} />
                            <Info label="角色代碼" value={role.code} />
                            <Info
                                label="類型"
                                value={role.is_system ? '系統角色' : '自訂角色'}
                            />
                            <Info
                                label="保護狀態"
                                value={role.is_protected ? '不可刪除' : '可刪除'}
                            />
                            <Info label="描述" value={role.description} wide />
                        </dl>
                    </section>

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            Capabilities
                        </h3>
                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            {Object.keys(groupedCapabilities).length === 0 && (
                                <p className="text-sm text-gray-500">
                                    尚未指派 capability
                                </p>
                            )}
                            {Object.entries(groupedCapabilities).map(
                                ([group, capabilities]) => (
                                    <div
                                        key={group}
                                        className="rounded-md border border-gray-200 p-4"
                                    >
                                        <h4 className="text-sm font-semibold text-gray-950">
                                            {group}
                                        </h4>
                                        <div className="mt-3 space-y-2">
                                            {capabilities.map((capability) => (
                                                <div
                                                    key={capability.id}
                                                    className="rounded-md bg-gray-50 px-3 py-2"
                                                >
                                                    <div className="text-sm font-medium text-gray-950">
                                                        {capability.name}
                                                    </div>
                                                    <div className="mt-1 break-all text-xs text-gray-500">
                                                        {capability.code}
                                                    </div>
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </section>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton
                                onClick={destroyRole}
                                disabled={role.is_protected}
                            >
                                刪除角色
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
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
