import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ materials, categories, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.materials.create);
    const canUpdate = can(CAPABILITIES.materials.update);
    const canDelete = can(CAPABILITIES.materials.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        category: filters.category ?? '',
        stock: filters.stock ?? '',
    });

    const submit = (event) => {
        event.preventDefault();

        get(route('materials.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyMaterial = (material) => {
        if (!window.confirm(`確定要刪除「${material.name}」嗎？`)) {
            return;
        }

        router.delete(route('materials.destroy', material.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        材料管理
                    </h2>
                    {canCreate && (
                        <Link href={route('materials.create')}>
                            <PrimaryButton>新增材料</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="材料管理" />

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
                            placeholder="搜尋材料名稱、規格、單位"
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.category}
                            onChange={(event) =>
                                setData('category', event.target.value)
                            }
                        >
                            <option value="">全部分類</option>
                            {categories.map((category) => (
                                <option key={category.id} value={category.id}>
                                    {category.name}
                                </option>
                            ))}
                        </select>
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.stock}
                            onChange={(event) =>
                                setData('stock', event.target.value)
                            }
                        >
                            <option value="">全部庫存</option>
                            <option value="low">低於安全庫存</option>
                        </select>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('materials.index')}>
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
                                        <Header>材料</Header>
                                        <Header>分類</Header>
                                        <Header>尺寸</Header>
                                        <Header align="right">成本</Header>
                                        <Header align="right">報價</Header>
                                        <Header align="right">庫存</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {materials.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 7 : 6}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有材料資料
                                            </td>
                                        </tr>
                                    )}

                                    {materials.data.map((material) => (
                                        <tr
                                            key={material.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'materials.show',
                                                        material.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {material.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {material.spec || '未填規格'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {material.category?.name ||
                                                    '未分類'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {dimension(material)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(material.cost_price)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(material.sale_price)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm">
                                                <div
                                                    className={
                                                        isLowStock(material)
                                                            ? 'font-semibold text-red-700'
                                                            : 'text-gray-700'
                                                    }
                                                >
                                                    {number(
                                                        material.current_stock,
                                                    )}{' '}
                                                    {material.unit}
                                                </div>
                                                <div className="mt-1 text-xs text-gray-500">
                                                    安全 {number(material.safe_stock)}
                                                </div>
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'materials.edit',
                                                                    material.id,
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
                                                                    destroyMaterial(
                                                                        material,
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

                    {materials.links.length > 3 && (
                        <div className="flex flex-wrap gap-2">
                            {materials.links.map((link) => (
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
                'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function dimension(material) {
    const parts = [
        material.length && `長 ${number(material.length)}`,
        material.width && `寬 ${number(material.width)}`,
        material.thickness && `厚 ${number(material.thickness)}`,
    ].filter(Boolean);

    return parts.length ? parts.join(' / ') : '未填';
}

function isLowStock(material) {
    return Number(material.current_stock ?? 0) <= Number(material.safe_stock ?? 0);
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
