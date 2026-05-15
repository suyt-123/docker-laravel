import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import WorkerForm from './Partials/WorkerForm';

export default function Create({ workCrews, users }) {
    return (
        <AuthenticatedLayout header={<h2 className="text-xl font-semibold leading-tight text-gray-800">新增師傅</h2>}>
            <Head title="新增師傅" />
            <div className="py-8"><div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8"><div className="bg-white p-6 shadow-sm sm:rounded-lg"><WorkerForm workCrews={workCrews} users={users} submitLabel="建立師傅" /></div></div></div>
        </AuthenticatedLayout>
    );
}
