import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ workCrews, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.workCrews.create);
    const canUpdate = can(CAPABILITIES.workCrews.update);
    const canDelete = can(CAPABILITIES.workCrews.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({ search: filters.search ?? '' });

    const submit = (event) => {
        event.preventDefault();
        get(route('work-crews.index'), { preserveState: true, replace: true });
    };

    const destroyCrew = (crew) => {
        if (window.confirm(`確定要刪除「${crew.name}」嗎？`)) {
            router.delete(route('work-crews.destroy', crew.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">工班管理</h2>
                    {canCreate && <Link href={route('work-crews.create')}><PrimaryButton>新增工班</PrimaryButton></Link>}
                </div>
            }
        >
            <Head title="工班管理" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>}

                    <form onSubmit={submit} className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg sm:flex-row sm:flex-wrap sm:items-center">
                        <TextInput className="w-full sm:max-w-sm" value={data.search} onChange={(event) => setData('search', event.target.value)} placeholder="搜尋工班、負責人、電話" />
                        <PrimaryButton disabled={processing}>搜尋</PrimaryButton>
                        <Link href={route('work-crews.index')}><SecondaryButton type="button">清除</SecondaryButton></Link>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>工班</Header>
                                        <Header>擅長工項</Header>
                                        <Header align="right">師傅</Header>
                                        <Header align="right">派工</Header>
                                        <Header align="right">日薪</Header>
                                        {canManage && <Header align="right">操作</Header>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {workCrews.data.length === 0 && <tr><td colSpan={canManage ? 6 : 5} className="px-4 py-10 text-center text-sm text-gray-500">目前沒有工班資料</td></tr>}
                                    {workCrews.data.map((crew) => (
                                        <tr key={crew.id} className="align-top hover:bg-gray-50">
                                            <td className="px-4 py-4">
                                                <Link href={route('work-crews.show', crew.id)} className="font-medium text-gray-950 hover:text-indigo-700">{crew.name}</Link>
                                                <div className="mt-1 text-sm text-gray-500">{crew.leader_name || '未填負責人'} · {crew.phone || '未填電話'}</div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">{crew.specialties?.length ? crew.specialties.join('、') : '未填'}</td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">{crew.workers_count}</td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">{crew.dispatches_count}</td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">{money(crew.daily_rate)}</td>
                                            {canManage && (
                                                <td className="px-4 py-4 text-right text-sm">
                                                    <div className="flex justify-end gap-3">
                                                        {canUpdate && <Link href={route('work-crews.edit', crew.id)} className="font-medium text-indigo-700 hover:text-indigo-900">編輯</Link>}
                                                        {canDelete && <button type="button" onClick={() => destroyCrew(crew)} className="font-medium text-red-700 hover:text-red-900">刪除</button>}
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
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) {
    return <th className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${align === 'right' ? 'text-right' : 'text-left'}`}>{children}</th>;
}

function money(value) {
    return value ? `NT$ ${Number(value).toLocaleString()}` : '未填';
}
