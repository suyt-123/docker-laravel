import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import PurchaseOrderForm from './Partials/PurchaseOrderForm';

export default function Create({ options, statuses, purchaseOrderNo }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增採購單
                </h2>
            }
        >
            <Head title="新增採購單" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <PurchaseOrderForm
                            options={options}
                            statuses={statuses}
                            purchaseOrderNo={purchaseOrderNo}
                            submitLabel="建立採購單"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
