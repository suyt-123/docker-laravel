import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ user }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.users.update);
    const canDelete = can(CAPABILITIES.users.delete);

    const destroyUser = () => {
        if (window.confirm(`確定要刪除「${user.name}」嗎？`)) {
            router.delete(route('users.destroy', user.id));
        }
    };

    const groupedCapabilities = groupCapabilities(user.capabilities ?? []);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {user.name}
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('users.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('users.edit', user.id)}>
                                <PrimaryButton>編輯使用者</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={user.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            帳號資訊
                        </h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info label="姓名" value={user.name} />
                            <Info label="Email" value={user.email} />
                            <Info
                                label="Email 驗證"
                                value={
                                    user.email_verified
                                        ? `已驗證 ${dateTime(user.email_verified_at)}`
                                        : '未驗證'
                                }
                            />
                            <Info
                                label="建立時間"
                                value={dateTime(user.created_at)}
                            />
                        </dl>
                    </section>

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            角色
                        </h3>
                        <div className="mt-5 flex flex-wrap gap-2">
                            {user.roles.length === 0 && (
                                <span className="text-sm text-gray-500">
                                    尚未指派角色
                                </span>
                            )}
                            {user.roles.map((role) => (
                                <span
                                    key={role.id}
                                    className="rounded-full bg-indigo-50 px-3 py-1.5 text-sm font-medium text-indigo-700"
                                >
                                    {role.name}
                                </span>
                            ))}
                        </div>
                    </section>

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            合併 Capabilities
                        </h3>
                        <div className="mt-5 grid gap-4 md:grid-cols-2">
                            {Object.keys(groupedCapabilities).length === 0 && (
                                <p className="text-sm text-gray-500">
                                    尚無權限
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
                                        <div className="mt-3 flex flex-wrap gap-2">
                                            {capabilities.map((capability) => (
                                                <span
                                                    key={capability.id}
                                                    className="rounded-full bg-gray-100 px-2.5 py-1 text-xs text-gray-700"
                                                >
                                                    {capability.name}
                                                </span>
                                            ))}
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </section>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyUser}>
                                刪除使用者
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 text-sm text-gray-950">{value || '未填'}</dd>
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

function dateTime(value) {
    return value ? new Date(value).toLocaleString('zh-TW') : '未填';
}
