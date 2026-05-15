import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DispatchForm from './Partials/DispatchForm';

export default function Create({ options, statuses }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增派工
                </h2>
            }
        >
            <Head title="新增派工" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <DispatchForm
                            options={options}
                            statuses={statuses}
                            submitLabel="建立派工"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
