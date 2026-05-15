import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import WorkCrewForm from './Partials/WorkCrewForm';

export default function Edit({ workCrew }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">編輯工班</h2>}>
            <Head title={`編輯工班 - ${workCrew.name}`} />
            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <WorkCrewForm workCrew={workCrew} submitLabel="儲存變更" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
