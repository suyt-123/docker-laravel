import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Index({ records, filters, types, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canCreate = can(CAPABILITIES.financialRecords.create);
    const canUpdate = can(CAPABILITIES.financialRecords.update);
    const canDelete = can(CAPABILITIES.financialRecords.delete);
    const canManage = canUpdate || canDelete;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        status: filters.status ?? '',
        type: filters.type ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('financial-records.index'), {
            preserveState: true,
            replace: true,
        });
    };

    const destroyRecord = (record) => {
        if (window.confirm(`確定要刪除「${record.title}」嗎？`)) {
            router.delete(route('financial-records.destroy', record.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        財務收款
                    </h2>
                    {canCreate && (
                        <Link href={route('financial-records.create')}>
                            <PrimaryButton>新增收款</PrimaryButton>
                        </Link>
                    )}
                </div>
            }
        >
            <Head title="財務收款" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form onSubmit={submit} className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:items-center">
                        <TextInput className="w-full lg:max-w-sm" value={data.search} onChange={(event) => setData('search', event.target.value)} placeholder="搜尋款項、案件、客戶" />
                        <select className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.type} onChange={(event) => setData('type', event.target.value)}>
                            <option value="">全部類型</option>
                            {Object.entries(types).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <select className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500" value={data.status} onChange={(event) => setData('status', event.target.value)}>
                            <option value="">全部狀態</option>
                            {Object.entries(statuses).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
                        </select>
                        <PrimaryButton disabled={processing}>搜尋</PrimaryButton>
                        <Link href={route('financial-records.index')}><SecondaryButton type="button">清除</SecondaryButton></Link>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>款項</Header>
                                        <Header>案件 / 客戶</Header>
                                        <Header>類型</Header>
                                        <Header>日期</Header>
                                        <Header align="right">金額</Header>
                                        <Header>狀態</Header>
                                        {canManage && <Header align="right">操作</Header>}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {records.data.length === 0 && <tr><td colSpan={canManage ? 7 : 6} className="px-4 py-10 text-center text-sm text-gray-500">目前沒有收款紀錄</td></tr>}
                                    {records.data.map((record) => (
                                        <tr key={record.id} className="align-top hover:bg-gray-50">
                                            <td className="px-4 py-4">
                                                <Link href={route('financial-records.show', record.id)} className="font-medium text-gray-950 hover:text-indigo-700">{record.title}</Link>
                                                {record.is_overdue && <div className="mt-1 text-sm font-medium text-red-700">逾期未收</div>}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <Link href={route('projects.show', record.project.id)} className="font-medium hover:text-indigo-700">{record.project.project_no}</Link>
                                                <div className="mt-1 text-gray-500">{record.project.name} · {record.project.customer?.name || '未填客戶'}</div>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">{types[record.type] ?? record.type}</td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>應收 {record.due_date || '未填'}</div>
                                                <div className="mt-1 text-gray-500">實收 {record.paid_date || '未收'}</div>
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-medium text-gray-950">{money(record.amount)}</td>
                                            <td className="px-4 py-4 text-sm"><Status value={record.status} label={statuses[record.status] ?? record.status} overdue={record.is_overdue} /></td>
                                            {canManage && <td className="px-4 py-4 text-right text-sm"><div className="flex justify-end gap-3">{canUpdate && <Link href={route('financial-records.edit', record.id)} className="font-medium text-indigo-700 hover:text-indigo-900">編輯</Link>}{canDelete && <button type="button" onClick={() => destroyRecord(record)} className="font-medium text-red-700 hover:text-red-900">刪除</button>}</div></td>}
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

function Status({ value, label, overdue }) {
    const color = value === 'paid' ? 'bg-emerald-50 text-emerald-700' : overdue || value === 'overdue' ? 'bg-red-50 text-red-700' : 'bg-slate-100 text-slate-700';
    return <span className={`inline-flex rounded-full px-2.5 py-1 text-xs font-medium ${color}`}>{label}</span>;
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
