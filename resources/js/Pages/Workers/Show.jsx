import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ worker }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.workers.update);
    const canDelete = can(CAPABILITIES.workers.delete);
    const destroyWorker = () => { if (window.confirm(`確定要刪除「${worker.name}」嗎？`)) router.delete(route('workers.destroy', worker.id)); };
    return (
        <AuthenticatedLayout header={<div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between"><h2 className="text-xl font-semibold leading-tight text-gray-800">{worker.name}</h2><div className="flex gap-2"><Link href={route('workers.index')}><SecondaryButton type="button">返回列表</SecondaryButton></Link>{canUpdate && <Link href={route('workers.edit', worker.id)}><PrimaryButton>編輯師傅</PrimaryButton></Link>}</div></div>}>
            <Head title={worker.name} />
            <div className="py-8"><div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                {flash.success && <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>}
                <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 className="text-base font-semibold text-gray-950">師傅資訊</h3>
                    <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                        <Info label="綁定帳號" value={worker.user ? `${worker.user.name} · ${worker.user.email}` : '未綁定'} /><Info label="所屬工班" value={worker.work_crew?.name} /><Info label="電話" value={worker.phone} /><Info label="職務" value={worker.role} /><Info label="日薪" value={money(worker.daily_rate)} /><Info label="保險到期" value={worker.insurance_expires_at} /><Info label="狀態" value={worker.is_active ? '啟用' : '停用'} /><Info label="證照 / 訓練" value={worker.certifications?.join('、')} wide /><Info label="備註" value={worker.note} wide />
                    </dl>
                </section>
                <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                    <h3 className="text-base font-semibold text-gray-950">近期派工</h3>
                    <div className="mt-5 divide-y divide-gray-100">
                        {worker.dispatches.length === 0 && <p className="text-sm text-gray-500">尚無派工紀錄</p>}
                        {worker.dispatches.map((dispatch) => <div key={dispatch.id} className="flex items-center justify-between gap-4 py-3"><div><Link href={route('dispatches.show', dispatch.id)} className="font-medium text-gray-950 hover:text-indigo-700">{dispatch.work_item}</Link><div className="mt-1 text-sm text-gray-500">{dispatch.project?.project_no} · {dispatch.project?.name}</div></div><div className="text-sm text-gray-600">{dispatch.scheduled_date}</div></div>)}
                    </div>
                </section>
                {canDelete && <div className="flex justify-end"><DangerButton onClick={destroyWorker}>刪除師傅</DangerButton></div>}
            </div></div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) { return <div className={wide ? 'sm:col-span-2' : ''}><dt className="text-sm font-medium text-gray-500">{label}</dt><dd className="mt-1 whitespace-pre-line text-sm text-gray-950">{value || '未填'}</dd></div>; }
function money(value) { return value ? `NT$ ${Number(value).toLocaleString()}` : null; }
