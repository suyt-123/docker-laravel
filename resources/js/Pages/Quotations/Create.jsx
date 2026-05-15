import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import QuotationForm from './Partials/QuotationForm';

export default function Create({ options, statuses, quotationNo }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增報價單
                </h2>
            }
        >
            <Head title="新增報價單" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <QuotationForm
                            options={options}
                            statuses={statuses}
                            quotationNo={quotationNo}
                            submitLabel="建立報價單"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
