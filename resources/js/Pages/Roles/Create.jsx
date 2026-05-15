import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import RoleForm from './Partials/RoleForm';

export default function Create({ capabilities }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增角色
                </h2>
            }
        >
            <Head title="新增角色" />

            <div className="py-8">
                <div className="mx-auto max-w-5xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <RoleForm
                            capabilities={capabilities}
                            submitLabel="建立角色"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
