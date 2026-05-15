import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ProgressLogForm from './Partials/ProgressLogForm';

export default function Edit({ progressLog, options }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    編輯工程日誌
                </h2>
            }
        >
            <Head title="編輯工程日誌" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <ProgressLogForm
                            progressLog={progressLog}
                            options={options}
                            submitLabel="儲存日誌"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
