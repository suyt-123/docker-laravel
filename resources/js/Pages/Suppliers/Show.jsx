import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ supplier }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.suppliers.update);
    const canDelete = can(CAPABILITIES.suppliers.delete);

    const destroySupplier = () => {
        if (window.confirm(`確定要刪除「${supplier.name}」嗎？`)) {
            router.delete(route('suppliers.destroy', supplier.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {supplier.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {supplier.is_active ? '啟用' : '停用'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('suppliers.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('suppliers.edit', supplier.id)}>
                                <PrimaryButton>編輯供應商</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={supplier.name} />
            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            供應商資訊
                        </h3>
                        <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                            <Info label="聯絡人" value={supplier.contact_name} />
                            <Info label="電話" value={supplier.phone} />
                            <Info label="Email" value={supplier.email} />
                            <Info label="統編" value={supplier.tax_id} />
                            <Info label="付款條件" value={supplier.payment_terms} />
                            <Info label="地址" value={supplier.address} wide />
                            <Info label="備註" value={supplier.note} wide />
                        </dl>
                    </section>

                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            近期採購單
                        </h3>
                        <div className="mt-5 divide-y divide-gray-100">
                            {supplier.purchase_orders.length === 0 && (
                                <p className="text-sm text-gray-500">
                                    尚無採購單
                                </p>
                            )}
                            {supplier.purchase_orders.map((order) => (
                                <div
                                    key={order.id}
                                    className="flex items-center justify-between gap-4 py-3"
                                >
                                    <Link
                                        href={route('purchase-orders.show', order.id)}
                                        className="font-medium text-gray-950 hover:text-indigo-700"
                                    >
                                        {order.purchase_order_no}
                                    </Link>
                                    <div className="text-sm text-gray-600">
                                        {money(order.total)}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </section>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroySupplier}>
                                刪除供應商
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

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}
