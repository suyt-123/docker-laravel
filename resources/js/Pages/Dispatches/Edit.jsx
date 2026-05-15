import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import DispatchForm from './Partials/DispatchForm';

export default function Edit({ dispatch, options, statuses }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    編輯派工
                </h2>
            }
        >
            <Head title={`編輯派工 - ${dispatch.work_item}`} />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <DispatchForm
                            dispatch={dispatch}
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
