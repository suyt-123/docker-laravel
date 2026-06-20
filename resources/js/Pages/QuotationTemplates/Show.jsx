import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';

export default function Show({ template, statuses, formulaTypes }) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.quotationTemplates.update);
    const canDelete = can(CAPABILITIES.quotationTemplates.delete);

    const destroyTemplate = () => {
        if (window.confirm(`確定要刪除「${template.name}」嗎？`)) {
            router.delete(route('quotation-templates.destroy', template.id));
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {template.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {statuses[template.status] ?? template.status}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('quotation-templates.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('quotation-templates.edit', template.id)}>
                                <PrimaryButton>編輯模板</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={template.name} />
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
                                模板資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info label="類型" value={template.type} />
                                <Info
                                    label="預設利潤率"
                                    value={`${template.profit_rate}%`}
                                />
                                <Info label="預設稅金" value={money(template.tax)} />
                                <Info
                                    label="預設折扣"
                                    value={money(template.discount)}
                                />
                                <Info label="備註" value={template.note} wide />
                            </dl>
                        </section>
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                工程參數
                            </h3>
                            <div className="mt-5 space-y-3">
                                {template.parameter_definitions.length === 0 && (
                                    <p className="text-sm text-gray-500">
                                        無工程參數
                                    </p>
                                )}
                                {template.parameter_definitions.map((parameter) => (
                                    <div
                                        key={parameter.key}
                                        className="rounded-md bg-gray-50 px-3 py-2 text-sm"
                                    >
                                        <div className="font-medium text-gray-950">
                                            {parameter.label}
                                        </div>
                                        <div className="mt-1 text-gray-500">
                                            {parameter.key}
                                            {parameter.unit
                                                ? ` · ${parameter.unit}`
                                                : ''}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </section>
                    </div>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-base font-semibold text-gray-950">
                                模板明細
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>項目</Header>
                                        <Header>規格</Header>
                                        <Header>公式</Header>
                                        <Header align="right">單價</Header>
                                        <Header align="right">成本</Header>
                                        <Header align="right">損耗</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {template.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-4 text-sm font-medium text-gray-950">
                                                {item.name}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {item.spec || '未填'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {formulaTypes[item.formula_type] ??
                                                    item.formula_type}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(item.unit_price)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(item.cost_price)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {item.waste_rate}%
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    {canDelete && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyTemplate}>
                                刪除模板
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
