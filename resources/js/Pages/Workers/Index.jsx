import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ workers, filters }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.workers.create);
    const canUpdate = can(CAPABILITIES.workers.update);
    const canDelete = can(CAPABILITIES.workers.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({ search: filters.search ?? '', active: filters.active ?? '' });
    const submit = (event) => { event.preventDefault(); get(route('workers.index'), { preserveState: true, replace: true }); };
    const destroyWorker = (worker) => { if (window.confirm(`確定要刪除「${worker.name}」嗎？`)) router.delete(route('workers.destroy', worker.id)); };

    return (
        <AuthenticatedLayout header={<div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"><h2 className="text-xl font-semibold leading-tight text-gray-800">師傅管理</h2>{canCreate && <Link href={route('workers.create')}><PrimaryButton>新增師傅</PrimaryButton></Link>}</div>}>
            <Head title="師傅管理" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>}
                    <form onSubmit={submit} className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:flex-wrap lg:items-center">
                        <TextInput className="w-full lg:max-w-sm" value={data.search} onChange={(event) => setData('search', event.target.value)} placeholder="搜尋師傅、工班、電話、職務" />
                        <select className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.active} onChange={(event) => setData('active', event.target.value)}>
                            <option value="">全部狀態</option><option value="1">啟用</option><option value="0">停用</option>
                        </select>
                        <PrimaryButton disabled={processing}>搜尋</PrimaryButton>
                        <Link href={route('workers.index')}><SecondaryButton type="button">清除</SecondaryButton></Link>
                    </form>
                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg"><div className="overflow-x-auto"><table className="min-w-full divide-y divide-gray-200">
                        <thead className="bg-gray-50"><tr><Header>師傅</Header><Header>登入帳號</Header><Header>工班</Header><Header>證照</Header><Header>保險</Header><Header align="right">日薪</Header><Header>狀態</Header>{canManage && <Header align="right">操作</Header>}</tr></thead>
                        <tbody className="divide-y divide-gray-100 bg-white">
                            {workers.data.length === 0 && <tr><td colSpan={canManage ? 8 : 7} className="px-4 py-10 text-center text-sm text-gray-500">目前沒有師傅資料</td></tr>}
                            {workers.data.map((worker) => (
                                <tr key={worker.id} className="align-top hover:bg-gray-50">
                                    <td className="px-4 py-4"><Link href={route('workers.show', worker.id)} className="font-medium text-gray-950 hover:text-indigo-700">{worker.name}</Link><div className="mt-1 text-sm text-gray-500">{worker.role || '未填職務'} · {worker.phone || '未填電話'}</div></td>
                                    <td className="px-4 py-4 text-sm text-gray-700">{worker.user ? <span>{worker.user.name}<span className="mt-1 block text-xs text-gray-500">{worker.user.email}</span></span> : '未綁定'}</td>
                                    <td className="px-4 py-4 text-sm text-gray-700">{worker.work_crew?.name || '未分工班'}</td>
                                    <td className="px-4 py-4 text-sm text-gray-700">{worker.certifications?.length ? worker.certifications.join('、') : '未填'}</td>
                                    <td className="px-4 py-4 text-sm text-gray-700">{worker.insurance_expires_at || '未填'}</td>
                                    <td className="px-4 py-4 text-right text-sm text-gray-700">{money(worker.daily_rate)}</td>
                                    <td className="px-4 py-4 text-sm text-gray-700">{worker.is_active ? '啟用' : '停用'}</td>
                                    {canManage && <td className="px-4 py-4 text-right text-sm"><div className="flex justify-end gap-3">{canUpdate && <Link href={route('workers.edit', worker.id)} className="font-medium text-indigo-700 hover:text-indigo-900">編輯</Link>}{canDelete && <button type="button" onClick={() => destroyWorker(worker)} className="font-medium text-red-700 hover:text-red-900">刪除</button>}</div></td>}
                                </tr>
                            ))}
                        </tbody>
                    </table></div></div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) { return <th className={`px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500 ${align === 'right' ? 'text-right' : 'text-left'}`}>{children}</th>; }
function money(value) { return value ? `NT$ ${Number(value).toLocaleString()}` : '未填'; }
