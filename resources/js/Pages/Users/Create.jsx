import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import UserForm from './Partials/UserForm';

export default function Create({ roles }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增使用者
                </h2>
            }
        >
            <Head title="新增使用者" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <UserForm roles={roles} submitLabel="建立使用者" />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
