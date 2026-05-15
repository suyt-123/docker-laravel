import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import QuotationTemplateForm from './Partials/QuotationTemplateForm';

export default function Create({ options, statuses, formulaTypes }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增報價模板
                </h2>
            }
        >
            <Head title="新增報價模板" />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <QuotationTemplateForm
                            options={options}
                            statuses={statuses}
                            formulaTypes={formulaTypes}
                            submitLabel="建立模板"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
