import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ roles, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.roles.create);
    const canUpdate = can(CAPABILITIES.roles.update);
    const canDelete = can(CAPABILITIES.roles.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        get(route('roles.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyRole = (role) => {
        if (!window.confirm(`確定要刪除「${role.name}」嗎？`)) {
            return;
        }

        router.delete(route('roles.destroy', role.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        角色權限
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('roles.matrix')}>
                            <SecondaryButton type="button">
                                權限矩陣
                            </SecondaryButton>
                        </Link>
                        {canCreate && (
                            <Link href={route('roles.create')}>
                                <PrimaryButton>新增角色</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title="角色權限" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center"
                    >
                        <TextInput
                            className="w-full lg:max-w-sm"
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋角色名稱、代碼"
                        />
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('roles.index')}>
                                <SecondaryButton type="button">
                                    清除
                                </SecondaryButton>
                            </Link>
                        </div>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>角色</Header>
                                        <Header>類型</Header>
                                        <Header align="right">
                                            Capabilities
                                        </Header>
                                        <Header align="right">使用者</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {roles.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 5 : 4}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有角色資料
                                            </td>
                                        </tr>
                                    )}

                                    {roles.data.map((role) => (
                                        <tr
                                            key={role.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'roles.show',
                                                        role.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {role.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {role.code}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm">
                                                <span
                                                    className={[
                                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                                        role.is_system
                                                            ? 'bg-gray-100 text-gray-700'
                                                            : 'bg-indigo-50 text-indigo-700',
                                                    ].join(' ')}
                                                >
                                                    {role.is_system
                                                        ? '系統角色'
                                                        : '自訂角色'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {role.capabilities_count}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {role.users_count}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'roles.edit',
                                                                    role.id,
                                                                )}
                                                                className="font-medium text-indigo-700 hover:text-indigo-900"
                                                            >
                                                                編輯
                                                            </Link>
                                                        )}
                                                        {canDelete && (
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    destroyRole(
                                                                        role,
                                                                    )
                                                                }
                                                                className="font-medium text-red-700 hover:text-red-900"
                                                            >
                                                                刪除
                                                            </button>
                                                        )}
                                                    </div>
                                                </td>
                                            )}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>

                    {roles.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {roles.links.map((link) => (
                                <Link
                                    key={`${link.label}-${link.url}`}
                                    href={link.url ?? '#'}
                                    preserveScroll
                                    className={[
                                        'rounded-md border px-3 py-2 text-sm',
                                        link.active
                                            ? 'border-indigo-600 bg-indigo-600 text-white'
                                            : 'border-gray-300 bg-white text-gray-700',
                                        !link.url
                                            ? 'pointer-events-none opacity-50'
                                            : 'hover:bg-gray-50',
                                    ].join(' ')}
                                    dangerouslySetInnerHTML={{
                                        __html: link.label,
                                    }}
                                />
                            ))}
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) {
    return (
        <th
            className={[
                'px-4 py-3 text-xs font-medium uppercase tracking-wider text-gray-500',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}
