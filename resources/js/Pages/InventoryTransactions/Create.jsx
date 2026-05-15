import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import InventoryTransactionForm from './Partials/InventoryTransactionForm';

export default function Create({ options, types }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增庫存異動
                </h2>
            }
        >
            <Head title="新增庫存異動" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <InventoryTransactionForm
                            options={options}
                            types={types}
                            submitLabel="建立異動"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
