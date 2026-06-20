import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ customer }) {
    const { flash = {} } = usePage().props;
    const { can, canViewProjectFinancials } = useAuthorization();
    const canUpdate = can(CAPABILITIES.customers.update);
    const canDelete = can(CAPABILITIES.customers.delete);
    const showProjectFinancials = canViewProjectFinancials();
    const canViewQuotations = can(CAPABILITIES.quotations.view);
    const canViewContact = can(CAPABILITIES.customers.viewContact);

    const destroyCustomer = () => {
        if (!window.confirm(`確定要刪除「${customer.name}」嗎？`)) {
            return;
        }

        router.delete(route('customers.destroy', customer.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {customer.name}
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('customers.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('customers.edit', customer.id)}>
                                <PrimaryButton>編輯客戶</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={customer.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    {canViewContact && (
                        <div className="grid gap-5 lg:grid-cols-3">
                            <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                                <h3 className="text-base font-semibold text-gray-950">
                                    基本資料
                                </h3>
                                <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                    <Info label="電話" value={customer.phone} />
                                    <Info label="LINE ID" value={customer.line_id} />
                                    <Info label="統一編號" value={customer.tax_id} />
                                    <Info label="客戶來源" value={customer.source} />
                                    <Info
                                        label="地址"
                                        value={customer.address}
                                        wide
                                    />
                                    <Info label="備註" value={customer.note} wide />
                                </dl>
                            </section>

                            <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                                <h3 className="text-base font-semibold text-gray-950">
                                    聯絡人
                                </h3>
                                <div className="mt-5 space-y-4">
                                    {customer.contacts.length === 0 && (
                                        <p className="text-sm text-gray-500">
                                            尚未建立聯絡人
                                        </p>
                                    )}
                                    {customer.contacts.map((contact) => (
                                        <div
                                            key={contact.id}
                                            className="border-b border-gray-100 pb-4 last:border-0 last:pb-0"
                                        >
                                            <div className="font-medium text-gray-950">
                                                {contact.name}
                                                {contact.is_primary && (
                                                    <span className="ms-2 rounded-full bg-indigo-50 px-2 py-0.5 text-xs text-indigo-700">
                                                        主要
                                                    </span>
                                                )}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-600">
                                                {contact.title || '未填職稱'}
                                            </div>
                                            <div className="mt-2 text-sm text-gray-600">
                                                {contact.phone || '未填電話'}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-600">
                                                {contact.email || '未填 Email'}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        </div>
                    )}

                    <div className="grid gap-5 lg:grid-cols-2">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                近期工程案件
                            </h3>
                            <div className="mt-5 divide-y divide-gray-100">
                                {customer.projects.length === 0 && (
                                    <p className="text-sm text-gray-500">
                                        尚無工程案件
                                    </p>
                                )}
                                {customer.projects.map((project) => (
                                    <div
                                        key={project.id}
                                        className="flex items-center justify-between gap-4 py-3"
                                    >
                                        <div>
                                            <div className="font-medium text-gray-950">
                                                {project.name}
                                            </div>
                                            <div className="mt-1 text-sm text-gray-500">
                                                {project.project_no} ·{' '}
                                                {project.status}
                                            </div>
                                        </div>
                                        {showProjectFinancials && (
                                            <div className="text-sm text-gray-600">
                                                NT${' '}
                                                {Number(
                                                    project.contract_amount,
                                                ).toLocaleString()}
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </section>

                        {canViewQuotations && (
                            <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                                <h3 className="text-base font-semibold text-gray-950">
                                    近期報價
                                </h3>
                                <div className="mt-5 divide-y divide-gray-100">
                                    {customer.quotations.length === 0 && (
                                        <p className="text-sm text-gray-500">
                                            尚無報價紀錄
                                        </p>
                                    )}
                                    {customer.quotations.map((quotation) => (
                                        <div
                                            key={quotation.id}
                                            className="flex items-center justify-between gap-4 py-3"
                                        >
                                            <div>
                                                <div className="font-medium text-gray-950">
                                                    {quotation.quotation_no}
                                                </div>
                                                <div className="mt-1 text-sm text-gray-500">
                                                    {quotation.status}
                                                </div>
                                            </div>
                                            <div className="text-sm text-gray-600">
                                                NT${' '}
                                                {Number(
                                                    quotation.total,
                                                ).toLocaleString()}
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </section>
                        )}
                    </div>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyCustomer}>
                                刪除客戶
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
        </div>
    );
}
