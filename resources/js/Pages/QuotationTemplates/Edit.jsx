import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import QuotationTemplateForm from './Partials/QuotationTemplateForm';

export default function Edit({ template, options, statuses, formulaTypes }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    編輯報價模板
                </h2>
            }
        >
            <Head title={`編輯模板 - ${template.name}`} />
            <div className="py-8">
                <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <QuotationTemplateForm
                            template={template}
                            options={options}
                            statuses={statuses}
                            formulaTypes={formulaTypes}
                            submitLabel="儲存變更"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
