import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import FinancialRecordForm from './Partials/FinancialRecordForm';

export default function Create({ options, types, statuses }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">新增收款紀錄</h2>}>
            <Head title="新增收款紀錄" />
            <div className="py-8"><div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8"><div className="bg-white p-6 shadow-sm sm:rounded-lg"><FinancialRecordForm options={options} types={types} statuses={statuses} submitLabel="建立收款紀錄" /></div></div></div>
        </AuthenticatedLayout>
    );
}
