import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ users, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.users.create);
    const canUpdate = can(CAPABILITIES.users.update);
    const canDelete = can(CAPABILITIES.users.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        get(route('users.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyUser = (user) => {
        if (!window.confirm(`確定要刪除「${user.name}」嗎？`)) {
            return;
        }

        router.delete(route('users.destroy', user.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        使用者管理
                    </h2>
                    {canCreate && (
                        <Link href={route('users.create')}>
                            <PrimaryButton>新增使用者</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="使用者管理" />

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
                            placeholder="搜尋姓名、Email、角色"
                        />
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('users.index')}>
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
                                        <Header>使用者</Header>
                                        <Header>角色</Header>
                                        <Header>驗證狀態</Header>
                                        <Header>建立時間</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {users.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 5 : 4}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有使用者資料
                                            </td>
                                        </tr>
                                    )}

                                    {users.data.map((user) => (
                                        <tr
                                            key={user.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'users.show',
                                                        user.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {user.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {user.email}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <RoleList roles={user.roles} />
                                            </td>
                                            <td className="px-4 py-4 text-sm">
                                                <span
                                                    className={[
                                                        'rounded-full px-2.5 py-1 text-xs font-medium',
                                                        user.email_verified
                                                            ? 'bg-emerald-50 text-emerald-700'
                                                            : 'bg-amber-50 text-amber-700',
                                                    ].join(' ')}
                                                >
                                                    {user.email_verified
                                                        ? '已驗證'
                                                        : '未驗證'}
                                                </span>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {dateTime(user.created_at)}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'users.edit',
                                                                    user.id,
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
                                                                    destroyUser(
                                                                        user,
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

                    {users.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {users.links.map((link) => (
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

function RoleList({ roles }) {
    if (!roles?.length) {
        return <span className="text-gray-500">未指派</span>;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {roles.map((role) => (
                <span
                    key={role.id}
                    className="rounded-full bg-indigo-50 px-2.5 py-1 text-xs font-medium text-indigo-700"
                >
                    {role.name}
                </span>
            ))}
        </div>
    );
}

function dateTime(value) {
    return value ? new Date(value).toLocaleString('zh-TW') : '未填';
}
