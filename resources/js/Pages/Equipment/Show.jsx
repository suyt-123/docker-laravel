import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, useForm, usePage } from '@inertiajs/react';

export default function Show({
    equipment,
    options,
    statuses,
    conditions,
    transactionTypes,
}) {
    const { flash = {} } = usePage().props;
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.equipment.update);
    const canCreateTransaction = can(CAPABILITIES.equipmentTransactions.create);

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {equipment.equipment_no} · {equipment.name}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {statuses[equipment.status] ?? equipment.status} /{' '}
                            {conditions[equipment.condition] ??
                                equipment.condition}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('equipment.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canUpdate && (
                            <Link href={route('equipment.edit', equipment.id)}>
                                <PrimaryButton>編輯機具</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={equipment.name} />

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
                                機具資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info label="分類" value={equipment.category?.name} />
                                <Info label="資產標籤" value={equipment.asset_tag} />
                                <Info label="品牌" value={equipment.brand} />
                                <Info label="型號" value={equipment.model} />
                                <Info label="序號" value={equipment.serial_no} />
                                <Info
                                    label="購買金額"
                                    value={money(equipment.purchase_price)}
                                />
                                <Info
                                    label="購買日期"
                                    value={formatDate(equipment.purchase_date)}
                                />
                                <Info
                                    label="保固到期日"
                                    value={formatDate(equipment.warranty_until)}
                                />
                                <Info label="備註" value={equipment.note} wide />
                            </dl>
                        </section>
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                目前狀態
                            </h3>
                            <dl className="mt-5 space-y-4">
                                <Info
                                    label="狀態"
                                    value={statuses[equipment.status] ?? equipment.status}
                                />
                                <Info
                                    label="機況"
                                    value={
                                        conditions[equipment.condition] ??
                                        equipment.condition
                                    }
                                />
                                <Info
                                    label="目前工程"
                                    value={
                                        equipment.current_project
                                            ? `${equipment.current_project.project_no} · ${equipment.current_project.name}`
                                            : null
                                    }
                                />
                                <Info
                                    label="目前工班"
                                    value={equipment.current_work_crew?.name}
                                />
                                <Info
                                    label="目前借用師傅"
                                    value={equipment.current_worker?.name}
                                />
                                <Info
                                    label="上次保養"
                                    value={formatDateTime(
                                        equipment.last_maintenance_at,
                                    )}
                                />
                                <Info
                                    label="下次保養"
                                    value={formatDateTime(
                                        equipment.next_maintenance_at,
                                    )}
                                />
                            </dl>
                        </section>
                    </div>

                    {canCreateTransaction && (
                        <TransactionForm
                            equipment={equipment}
                            options={options}
                            conditions={conditions}
                            transactionTypes={transactionTypes}
                        />
                    )}

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="flex flex-col gap-3 border-b border-gray-200 p-6 sm:flex-row sm:items-center sm:justify-between">
                            <h3 className="text-base font-semibold text-gray-950">
                                最近交易紀錄
                            </h3>
                            <Link
                                href={route('equipment-transactions.index', {
                                    search: equipment.equipment_no,
                                })}
                                className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                            >
                                查看全部
                            </Link>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>類型</Header>
                                        <Header>工程 / 使用者</Header>
                                        <Header>機況</Header>
                                        <Header>時間</Header>
                                        <Header>處理人</Header>
                                        <Header>備註</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {equipment.transactions.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan="6"
                                                className="px-4 py-10 text-center text-sm text-gray-500"
                                            >
                                                目前沒有交易紀錄
                                            </td>
                                        </tr>
                                    )}
                                    {equipment.transactions.map((transaction) => (
                                        <tr key={transaction.id}>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {transactionTypes[
                                                    transaction.type
                                                ] ?? transaction.type}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {targetText(transaction)}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {conditionText(
                                                    transaction,
                                                    conditions,
                                                )}
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
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function TransactionForm({ equipment, options, conditions, transactionTypes }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        type: 'check_out',
        project_id: equipment.current_project_id ?? '',
        worker_id: '',
        work_crew_id: equipment.current_work_crew_id ?? '',
        occurred_at: new Date().toISOString().slice(0, 16),
        due_at: '',
        condition_after: equipment.condition ?? 'good',
        from_location: '',
        to_location: '',
        note: '',
    });

    const submit = (event) => {
        event.preventDefault();
        post(route('equipment.transactions.store', equipment.id), {
            preserveScroll: true,
            onSuccess: () =>
                reset(
                    'worker_id',
                    'due_at',
                    'from_location',
                    'to_location',
                    'note',
                ),
        });
    };

    return (
        <form
            onSubmit={submit}
            className="space-y-5 bg-white p-6 shadow-sm sm:rounded-lg"
        >
            <h3 className="text-base font-semibold text-gray-950">
                新增機具交易
            </h3>

            <div className="grid gap-5 md:grid-cols-2 lg:grid-cols-3">
                <SelectField
                    id="type"
                    label="交易類型"
                    value={data.type}
                    onChange={(value) => setData('type', value)}
                    options={transactionTypes}
                    error={errors.type}
                    required
                />
                <OptionSelect
                    id="project_id"
                    label="工程案件"
                    value={data.project_id}
                    onChange={(value) => setData('project_id', value)}
                    options={options.projects}
                    error={errors.project_id}
                    emptyLabel="不指定"
                />
                <OptionSelect
                    id="work_crew_id"
                    label="工班"
                    value={data.work_crew_id}
                    onChange={(value) => setData('work_crew_id', value)}
                    options={options.workCrews}
                    error={errors.work_crew_id}
                    emptyLabel="不指定"
                />
                <OptionSelect
                    id="worker_id"
                    label="師傅"
                    value={data.worker_id}
                    onChange={(value) => setData('worker_id', value)}
                    options={options.workers}
                    error={errors.worker_id}
                    emptyLabel="不指定"
                />
                <Field
                    id="occurred_at"
                    type="datetime-local"
                    label="發生時間"
                    value={data.occurred_at}
                    onChange={(value) => setData('occurred_at', value)}
                    error={errors.occurred_at}
                    required
                />
                <Field
                    id="due_at"
                    type="datetime-local"
                    label="預計歸還時間"
                    value={data.due_at}
                    onChange={(value) => setData('due_at', value)}
                    error={errors.due_at}
                />
                <SelectField
                    id="condition_after"
                    label="交易後機況"
                    value={data.condition_after}
                    onChange={(value) => setData('condition_after', value)}
                    options={conditions}
                    error={errors.condition_after}
                />
                <Field
                    id="from_location"
                    label="來源位置"
                    value={data.from_location}
                    onChange={(value) => setData('from_location', value)}
                    error={errors.from_location}
                />
                <Field
                    id="to_location"
                    label="目的位置"
                    value={data.to_location}
                    onChange={(value) => setData('to_location', value)}
                    error={errors.to_location}
                />
            </div>

            <div>
                <InputLabel htmlFor="transaction_note" value="備註" />
                <textarea
                    id="transaction_note"
                    className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    rows="3"
                    value={data.note}
                    onChange={(event) => setData('note', event.target.value)}
                />
                <InputError message={errors.note} className="mt-2" />
            </div>

            <PrimaryButton disabled={processing}>新增交易</PrimaryButton>
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

function Header({ children }) {
    return (
        <th className="px-4 py-3 text-left text-xs font-semibold uppercase tracking-wide text-gray-500">
            {children}
        </th>
    );
}

function Field({
    id,
    label,
    value,
    onChange,
    error,
    type = 'text',
    required = false,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <TextInput
                id={id}
                type={type}
                className="mt-1 block w-full"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
            />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function SelectField({
    id,
    label,
    value,
    onChange,
    options,
    error,
    required = false,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} required={required} />
            <select
                id={id}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
                required={required}
            >
                {Object.entries(options).map(([key, label]) => (
                    <option key={key} value={key}>
                        {label}
                    </option>
                ))}
            </select>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

function OptionSelect({
    id,
    label,
    value,
    onChange,
    options,
    error,
    emptyLabel,
}) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <select
                id={id}
                className="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                value={value ?? ''}
                onChange={(event) => onChange(event.target.value)}
            >
                <option value="">{emptyLabel}</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.label ?? option.name}
                    </option>
                ))}
            </select>
            <InputError message={error} className="mt-2" />
        </div>
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

function conditionText(transaction, conditions) {
    const before =
        conditions[transaction.condition_before] ?? transaction.condition_before;
    const after =
        conditions[transaction.condition_after] ?? transaction.condition_after;

    if (!before && !after) {
        return '未填';
    }

    return `${before || '未填'} -> ${after || '未填'}`;
}

function formatDate(value) {
    if (!value) {
        return null;
    }

    return String(value).slice(0, 10);
}

function formatDateTime(value) {
    if (!value) {
        return null;
    }

    return String(value).replace('T', ' ').slice(0, 16);
}

function money(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }

    return `NT$ ${Number(value).toLocaleString()}`;
}
