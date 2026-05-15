import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ material }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.materials.update);
    const canDelete = can(CAPABILITIES.materials.delete);

    const destroyMaterial = () => {
        if (!window.confirm(`確定要刪除「${material.name}」嗎？`)) {
            return;
        }

        router.delete(route('materials.destroy', material.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {material.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {material.spec || '未填規格'}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('materials.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('materials.edit', material.id)}>
                                <PrimaryButton>編輯材料</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={material.name} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid gap-5 lg:grid-cols-3">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                            <h3 className="text-base font-semibold text-gray-950">
                                材料資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="分類"
                                    value={material.category?.name}
                                />
                                <Info label="單位" value={material.unit} />
                                <Info
                                    label="長度"
                                    value={material.length}
                                />
                                <Info label="寬度" value={material.width} />
                                <Info
                                    label="厚度"
                                    value={material.thickness}
                                />
                                <Info label="重量" value={material.weight} />
                            </dl>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                價格與庫存
                            </h3>
                            <dl className="mt-5 space-y-4">
                                <Info
                                    label="成本單價"
                                    value={money(material.cost_price)}
                                />
                                <Info
                                    label="報價單價"
                                    value={money(material.sale_price)}
                                />
                                <Info
                                    label="安全庫存"
                                    value={`${number(material.safe_stock)} ${material.unit}`}
                                />
                                <Info
                                    label="目前庫存"
                                    value={`${number(material.current_stock)} ${material.unit}`}
                                />
                            </dl>
                        </section>
                    </div>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <RelatedList
                            title="近期報價使用"
                            empty="尚無報價使用紀錄"
                            items={material.quotation_items}
                            render={(item) => (
                                <>
                                    <div>
                                        <div className="font-medium text-gray-950">
                                            {item.quotation?.quotation_no ||
                                                '報價單'}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500">
                                            {number(item.quantity)} {item.unit}
                                        </div>
                                    </div>
                                    <div className="text-sm text-gray-600">
                                        {money(item.subtotal)}
                                    </div>
                                </>
                            )}
                        />

                        <RelatedList
                            title="近期庫存異動"
                            empty="尚無庫存異動"
                            items={material.inventory_transactions}
                            render={(transaction) => (
                                <>
                                    <div>
                                        <div className="font-medium text-gray-950">
                                            {transaction.type}
                                        </div>
                                        <div className="mt-1 text-sm text-gray-500">
                                            {transaction.project?.name ||
                                                '未綁定案件'}
                                        </div>
                                    </div>
                                    <div className="text-sm text-gray-600">
                                        {number(transaction.quantity)}{' '}
                                        {transaction.unit}
                                    </div>
                                </>
                            )}
                        />
                    </div>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyMaterial}>
                                刪除材料
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
        </div>
    );
}

function RelatedList({ title, empty, items, render }) {
    return (
        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
            <h3 className="text-base font-semibold text-gray-950">{title}</h3>
            <div className="mt-5 divide-y divide-gray-100">
                {items.length === 0 && (
                    <p className="text-sm text-gray-500">{empty}</p>
                )}
                {items.map((item) => (
                    <div
                        key={item.id}
                        className="flex items-center justify-between gap-4 py-3"
                    >
                        {render(item)}
                    </div>
                ))}
            </div>
        </section>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
