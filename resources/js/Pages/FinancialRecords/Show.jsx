import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ record, types, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.financialRecords.update);
    const canDelete = can(CAPABILITIES.financialRecords.delete);
    const destroyRecord = () => {
        if (window.confirm(`確定要刪除「${record.title}」嗎？`)) router.delete(route('financial-records.destroy', record.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">{record.title}</h2>
                    <div className="flex gap-2">
                        <Link href={route('financial-records.index')}><SecondaryButton type="button">返回列表</SecondaryButton></Link>
                        {canUpdate && <Link href={route('financial-records.edit', record.id)}><PrimaryButton>編輯收款</PrimaryButton></Link>}
                    </div>
                </div>
            }
        >
            <Head title={record.title} />
            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{flash.success}</div>}
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">收款資訊</h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info label="工程案件" value={`${record.project.project_no} · ${record.project.name}`} />
                            <Info label="客戶" value={record.project.customer?.name} />
                            <Info label="款項類型" value={types[record.type]} />
                            <Info label="狀態" value={statuses[record.status]} />
                            <Info label="金額" value={money(record.amount)} />
                            <Info label="應收日期" value={record.due_date} />
                            <Info label="實收日期" value={record.paid_date} />
                            <Info label="逾期" value={record.is_overdue ? '是' : '否'} />
                            <Info label="備註" value={record.note} wide />
                        </dl>
                    </section>
                    {canDelete && <div className="flex justify-end"><DangerButton onClick={destroyRecord}>刪除收款</DangerButton></div>}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return <div className={wide ? 'sm:col-span-2' : ''}><dt className="text-sm font-medium text-gray-500">{label}</dt><dd className="mt-1 whitespace-pre-line text-sm text-gray-950">{value || '未填'}</dd></div>;
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
