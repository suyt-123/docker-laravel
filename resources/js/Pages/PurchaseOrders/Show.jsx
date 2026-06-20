import DangerButton from '@/Components/DangerButton';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, useForm, usePage } from '@inertiajs/react';

export default function Show({ order, statuses }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.purchaseOrders.update);
    const canDelete = can(CAPABILITIES.purchaseOrders.delete);
    const canReceive = can(CAPABILITIES.purchaseOrders.receive);

    const destroyOrder = () => {
        if (window.confirm(`確定要刪除「${order.purchase_order_no}」嗎？`)) {
            router.delete(route('purchase-orders.destroy', order.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {order.purchase_order_no}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {statuses[order.status] ?? order.status}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('purchase-orders.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate &&
                            ![
                                'partially_received',
                                'completed',
                                'cancelled',
                            ].includes(order.status) && (
                                <Link href={route('purchase-orders.edit', order.id)}>
                                    <PrimaryButton>編輯採購單</PrimaryButton>
                                </Link>
                            )}
                    </div>
                </div>
            }
        >
            <Head title={order.purchase_order_no} />
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
                                採購資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info label="供應商" value={order.supplier?.name} />
                                <Info
                                    label="狀態"
                                    value={statuses[order.status] ?? order.status}
                                />
                                <Info label="建立人" value={order.creator?.name} />
                                <Info
                                    label="採購日期"
                                    value={order.ordered_date}
                                />
                                <Info
                                    label="預計到貨日"
                                    value={order.expected_date}
                                />
                                <Info label="備註" value={order.note} wide />
                            </dl>
                        </section>
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                金額
                            </h3>
                            <dl className="mt-5 space-y-4">
                                <Info label="小計" value={money(order.subtotal)} />
                                <Info label="稅金" value={money(order.tax)} />
                                <Info label="折扣" value={money(order.discount)} />
                                <Info label="總額" value={money(order.total)} />
                            </dl>
                        </section>
                    </div>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-base font-semibold text-gray-950">
                                採購明細
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>材料</Header>
                                        <Header>規格</Header>
                                        <Header align="right">採購數量</Header>
                                        <Header align="right">已到貨</Header>
                                        <Header align="right">單位成本</Header>
                                        <Header align="right">小計</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {order.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-4 text-sm font-medium text-gray-950">
                                                {item.name}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {item.spec || '未填'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {number(item.quantity)} {item.unit}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {number(item.received_quantity)}{' '}
                                                {item.unit}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(item.unit_cost)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-medium text-gray-950">
                                                {money(item.subtotal)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {canReceive && order.can_receive && (
                        <ReceiveForm order={order} />
                    )}

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyOrder}>
                                刪除採購單
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ReceiveForm({ order }) {
    const { data, setData, post, processing, errors } = useForm({
        received_at: new Date().toISOString().slice(0, 16),
        items: order.items.map((item) => ({
            id: item.id,
            received_quantity: item.remaining_quantity,
            note: '',
        })),
    });

    const updateItem = (index, field, value) => {
        const items = [...data.items];
        items[index] = { ...items[index], [field]: value };
        setData('items', items);
    };

    const submit = (event) => {
        event.preventDefault();
        post(route('purchase-orders.receive', order.id));
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-5 bg-white p-6 shadow-sm sm:rounded-lg"
        >
            <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                <h3 className="text-base font-semibold text-gray-950">
                    到貨驗收
                </h3>
                <div className="sm:w-64">
                    <InputLabel htmlFor="received_at" value="驗收時間" />
                    <TextInput
                        id="received_at"
                        type="datetime-local"
                        className="mt-1 block w-full"
                        value={data.received_at}
                        onChange={(event) =>
                            setData('received_at', event.target.value)
                        }
                    />
                </div>
            </div>
            <div className="space-y-3">
                {order.items.map((item, index) => (
                    <div
                        key={item.id}
                        className="grid gap-3 rounded-md border border-gray-200 p-3 md:grid-cols-5"
                    >
                        <div className="md:col-span-2">
                            <div className="text-sm font-medium text-gray-950">
                                {item.name}
                            </div>
                            <div className="mt-1 text-sm text-gray-500">
                                未到貨 {number(item.remaining_quantity)} {item.unit}
                            </div>
                        </div>
                        <div>
                            <InputLabel value="本次到貨" />
                            <TextInput
                                type="number"
                                className="mt-1 block w-full"
                                value={data.items[index]?.received_quantity ?? 0}
                                onChange={(event) =>
                                    updateItem(
                                        index,
                                        'received_quantity',
                                        event.target.value,
                                    )
                                }
                                min="0"
                                max={item.remaining_quantity}
                                step="0.001"
                            />
                            {errors[`items.${index}.received_quantity`] && (
                                <div className="mt-2 text-sm text-red-600">
                                    {errors[`items.${index}.received_quantity`]}
                                </div>
                            )}
                        </div>
                        <div className="md:col-span-2">
                            <InputLabel value="備註" />
                            <TextInput
                                className="mt-1 block w-full"
                                value={data.items[index]?.note ?? ''}
                                onChange={(event) =>
                                    updateItem(index, 'note', event.target.value)
                                }
                            />
                        </div>
                    </div>
                ))}
            </div>
            <div className="flex justify-end">
                <PrimaryButton disabled={processing}>完成驗收並入庫</PrimaryButton>
            </div>
        </form>
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

function Header({ children, align = 'left' }) {
    return (
        <th
            className={[
                'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}
