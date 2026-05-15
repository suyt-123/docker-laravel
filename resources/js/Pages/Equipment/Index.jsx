import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({
    equipment,
    categories,
    statuses,
    conditions,
    filters,
}) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.equipment.create);
    const canUpdate = can(CAPABILITIES.equipment.update);
    const canDelete = can(CAPABILITIES.equipment.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        category: filters.category ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('equipment.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyEquipment = (item) => {
        if (window.confirm(`確定要刪除「${item.name}」嗎？`)) {
            router.delete(route('equipment.destroy', item.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        工具與機具
                    </h2>
                    {canCreate && (
                        <Link href={route('equipment.create')}>
                            <PrimaryButton>新增機具</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="工具與機具" />

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
                            placeholder="搜尋編號、名稱、品牌、型號、序號"
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
                            value={data.status}
                            onChange={(event) =>
                                setData('status', event.target.value)
                            }
                        >
                            <option value="">全部狀態</option>
                            {Object.entries(statuses).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('equipment.index')}>
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
                                        <Header>機具</Header>
                                        <Header>分類</Header>
                                        <Header>狀態</Header>
                                        <Header>目前位置</Header>
                                        <Header>下次保養</Header>
                                        {canManage && (
                                            <Header align="right">操作</Header>
                                        )}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {equipment.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={canManage ? 6 : 5}
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有工具與機具資料
                                            </td>
                                        </tr>
                                    )}

                                    {equipment.data.map((item) => (
                                        <tr
                                            key={item.id}
                                            className="align-top hover:bg-gray-50"
                                        >
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'equipment.show',
                                                        item.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {item.equipment_no} · {item.name}
                                                </Link>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {[item.brand, item.model]
                                                        .filter(Boolean)
                                                        .join(' / ') || '未填品牌型號'}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {item.category?.name || '未分類'}
                                            </td>
                                            <td className="px-4 py-4 text-sm">
                                                <div className="font-medium text-gray-950">
                                                    {statuses[item.status] ??
                                                        item.status}
                                                </div>
                                                <div className="mt-1 text-xs text-gray-500">
                                                    {conditions[item.condition] ??
                                                        item.condition}
                                                </div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {locationText(item)}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {formatDate(item.next_maintenance_at)}
                                            </td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && (
                                                            <Link
                                                                href={route(
                                                                    'equipment.edit',
                                                                    item.id,
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
                                                                    destroyEquipment(
                                                                        item,
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

                    {equipment.links && (
                        <div className="flex flex-wrap gap-2">
                            {equipment.links.map((link, index) => (
                                <Link
                                    key={index}
                                    href={link.url ?? '#'}
                                    className={`rounded-md border px-3 py-2 text-sm ${
                                        link.active
                                            ? 'border-gray-900 bg-gray-900 text-white'
                                            : 'border-gray-200 bg-white text-gray-700'
                                    } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
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
            className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${
                align === 'right' ? 'text-right' : 'text-left'
            }`}
        >
            {children}
        </th>
    );
}

function locationText(item) {
    if (item.current_project) {
        return `${item.current_project.project_no} · ${item.current_project.name}`;
    }

    if (item.current_worker) {
        return `借用：${item.current_worker.name}`;
    }

    if (item.current_work_crew) {
        return `工班：${item.current_work_crew.name}`;
    }

    return '倉庫 / 未配置';
}

function formatDate(value) {
    if (!value) {
        return '未排定';
    }

    return String(value).slice(0, 10);
}
