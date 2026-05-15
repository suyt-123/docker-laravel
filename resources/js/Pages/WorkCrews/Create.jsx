import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import WorkCrewForm from './Partials/WorkCrewForm';

export default function Create() {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">新增工班</h2>}>
            <Head title="新增工班" />
            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <WorkCrewForm submitLabel="建立工班" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
