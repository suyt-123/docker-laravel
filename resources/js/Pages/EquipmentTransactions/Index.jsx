import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Index({ transactions, filters, types }) {
    const { flash = {} } = usePage().props;
    const { data, setData, get, processing } = useForm({
        search: filters.search ?? '',
        type: filters.type ?? '',
    });

    const submit = (event) => {
        event.preventDefault();
        get(route('equipment-transactions.index'), {
            preserveState: true,
            replace: true,
        });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    機具交易紀錄
                </h2>
            }
        >
            <Head title="機具交易紀錄" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <form
                        onSubmit={submit}
                        className="flex flex-col gap-3 bg-white p-4 shadow-sm sm:rounded-lg lg:flex-row lg:flex-wrap lg:items-center"
                    >
                        <TextInput
                            className="w-full lg:max-w-sm"
                            value={data.search}
                            onChange={(event) =>
                                setData('search', event.target.value)
                            }
                            placeholder="搜尋機具、工程、師傅、工班、備註"
                        />
                        <select
                            className="rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                            value={data.type}
                            onChange={(event) =>
                                setData('type', event.target.value)
                            }
                        >
                            <option value="">全部類型</option>
                            {Object.entries(types).map(([key, label]) => (
                                <option key={key} value={key}>
                                    {label}
                                </option>
                            ))}
                        </select>
                        <div className="flex gap-2">
                            <PrimaryButton disabled={processing}>
                                搜尋
                            </PrimaryButton>
                            <Link href={route('equipment-transactions.index')}>
                                <SecondaryButton type="button">
                                    清除
                                </SecondaryButton>
                            </Link>
                        </div>
                    </form>

                    <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>機具</Header>
                                        <Header>類型</Header>
                                        <Header>工程 / 使用者</Header>
                                        <Header>時間</Header>
                                        <Header>處理人</Header>
                                        <Header>備註</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {transactions.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有機具交易紀錄
                                            </td>
                                        </tr>
                                    )}

                                    {transactions.data.map((transaction) => (
                                        <tr key={transaction.id}>
                                            <td className="px-4 py-4">
                                                <Link
                                                    href={route(
                                                        'equipment.show',
                                                        transaction.equipment.id,
                                                    )}
                                                    className="font-medium text-gray-950 hover:text-indigo-700"
                                                >
                                                    {
                                                        transaction.equipment
                                                            .equipment_no
                                                    }{' '}
                                                    ·{' '}
                                                    {transaction.equipment.name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {types[transaction.type] ??
                                                    transaction.type}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {targetText(transaction)}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                <div>
                                                    {transaction.occurred_at ||
                                                        '未填'}
                                                </div>
                                                {transaction.due_at && (
                                                    <div className="mt-1 text-xs text-gray-500">
                                                        預計歸還{' '}
                                                        {transaction.due_at}
                                                    </div>
                                                )}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {transaction.handler?.name ||
                                                    '系統'}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {transaction.note || '未填'}
                                            </td>
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

function Header({ children }) {
    return (
        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
            {children}
        </th>
    );
}

function targetText(transaction) {
    const parts = [];

    if (transaction.project) {
        parts.push(
            `${transaction.project.project_no} · ${transaction.project.name}`,
        );
    }

    if (transaction.work_crew) {
        parts.push(`工班：${transaction.work_crew.name}`);
    }

    if (transaction.worker) {
        parts.push(`師傅：${transaction.worker.name}`);
    }

    return parts.join(' / ') || '未指定';
}
