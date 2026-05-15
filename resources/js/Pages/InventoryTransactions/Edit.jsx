import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import InventoryTransactionForm from './Partials/InventoryTransactionForm';

export default function Edit({ transaction, options, types }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    編輯庫存異動
                </h2>
            }
        >
            <Head title="編輯庫存異動" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <InventoryTransactionForm
                            transaction={transaction}
                            options={options}
                            types={types}
                            submitLabel="儲存變更"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
