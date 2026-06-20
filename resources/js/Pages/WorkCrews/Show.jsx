import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ workCrew }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.workCrews.update);
    const canDelete = can(CAPABILITIES.workCrews.delete);
    const destroyCrew = () => {
        if (window.confirm(`確定要刪除「${workCrew.name}」嗎？`)) router.delete(route('work-crews.destroy', workCrew.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{workCrew.name}</h2>
                    <div className="flex gap-2">
                        <Link href={route('work-crews.index')}><SecondaryButton type="button">返回列表</SecondaryButton></Link>
                        {canUpdate && <Link href={route('work-crews.edit', workCrew.id)}><PrimaryButton>編輯工班</PrimaryButton></Link>}
                    </div>
                </div>
            }
        >
            <Head title={workCrew.name} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>}
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">工班資訊</h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info label="負責人" value={workCrew.leader_name} />
                            <Info label="電話" value={workCrew.phone} />
                            <Info label="預設日薪" value={money(workCrew.daily_rate)} />
                            <Info label="擅長工項" value={workCrew.specialties?.join('、')} />
                            <Info label="備註" value={workCrew.note} wide />
                        </dl>
                    </section>
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">師傅名單</h3>
                        <div className="mt-5 divide-y divide-gray-100">
                            {workCrew.workers.length === 0 && <p className="text-sm text-gray-500">尚無師傅</p>}
                            {workCrew.workers.map((worker) => (
                                <div key={worker.id} className="flex items-center justify-between gap-4 py-3">
                                    <div>
                                        <Link href={route('workers.show', worker.id)} className="font-medium text-gray-950 hover:text-indigo-700">{worker.name}</Link>
                                        <div className="mt-1 text-sm text-gray-500">{worker.role || '未填職務'} · {worker.phone || '未填電話'}</div>
                                    </div>
                                    <div className="text-sm text-gray-600">{worker.is_active ? '啟用' : '停用'}</div>
                                </div>
                            ))}
                        </div>
                    </section>
                    {canDelete && <div className="flex justify-end"><DangerButton onClick={destroyCrew}>刪除工班</DangerButton></div>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return <div className={wide ? 'sm:col-span-2' : ''}><dt className="text-sm font-medium text-gray-500">{label}</dt><dd className="mt-1 whitespace-pre-line text-sm text-gray-950">{value || '未填'}</dd></div>;
}

function money(value) {
    return value ? `NT$ ${Number(value).toLocaleString()}` : null;
}
