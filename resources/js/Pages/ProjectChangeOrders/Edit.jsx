import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ProjectChangeOrderForm from './Partials/ProjectChangeOrderForm';

export default function Edit({ order, options, statuses }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    編輯工程變更追加單
                </h2>
            }
        >
            <Head title={`編輯追加單 - ${order.title}`} />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <ProjectChangeOrderForm
                            order={order}
                            options={options}
                            statuses={statuses}
                            submitLabel="儲存變更"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
