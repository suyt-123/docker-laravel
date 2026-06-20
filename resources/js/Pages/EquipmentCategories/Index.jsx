import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ categories, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.equipmentCategories.create);
    const canUpdate = can(CAPABILITIES.equipmentCategories.update);
    const canDelete = can(CAPABILITIES.equipmentCategories.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('equipment-categories.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyCategory = (category) => {
        if (window.confirm(`確定要刪除「${category.name}」嗎？`)) {
            router.delete(route('equipment-categories.destroy', category.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        機具分類
                    </h2>
                    {canCreate && (
                        <Link href={route('equipment-categories.create')}>
                            <PrimaryButton>新增分類</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="機具分類" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:flex-wrap lg:items-center"
                    >
                        <TextInput
                            className="w-full lg:max-w-sm"
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋分類名稱或代碼"
                        />
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('equipment-categories.index')}>
                                <SecondaryButton type="button">
                                    清除
                                </SecondaryButton>
                            </Link>
                        </div>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <table className="min-w-full divide-y divide-gray-200">
                            <thead className="bg-gray-50">
                                <tr>
                                    <Header>分類</Header>
                                    <Header>代碼</Header>
                                    <Header align="right">設備數</Header>
                                    <Header>狀態</Header>
                                    {canManage && <Header align="right">操作</Header>}
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-gray-100 bg-white">
                                {categories.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={canManage ? 5 : 4}
                                            className="px-4 py-10 text-center text-sm text-gray-500"
                                        >
                                            目前沒有機具分類
                                        </td>
                                    </tr>
                                )}
                                {categories.data.map((category) => (
                                    <tr key={category.id}>
                                        <td className="px-4 py-4">
                                            <Link
                                                href={route(
                                                    'equipment-categories.show',
                                                    category.id,
                                                )}
                                                className="font-medium text-gray-950 hover:text-indigo-700"
                                            >
                                                {category.name}
                                            </Link>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {category.description || '未填說明'}
                                            </div>
                                        </td>
                                        <td className="px-4 py-4 text-sm text-gray-700">
                                            {category.code}
                                        </td>
                                        <td className="px-4 py-4 text-right text-sm text-gray-700">
                                            {category.equipment_count}
                                        </td>
                                        <td className="px-4 py-4 text-sm">
                                            {category.is_active ? '啟用' : '停用'}
                                        </td>
                                        {canManage && (
                                            <td className="px-4 py-4 text-right text-sm">
                                                <div className="flex justify-end gap-3">
                                                    {canUpdate && (
                                                        <Link
                                                            href={route(
                                                                'equipment-categories.edit',
                                                                category.id,
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
                                                                destroyCategory(
                                                                    category,
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
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) {
    return (
        <th
            className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${
                align === 'right' ? 'text-right' : 'text-left'
            }`}
        >
            {children}
        </th>
    );
}
