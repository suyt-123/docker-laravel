import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import EquipmentCategoryForm from './Partials/EquipmentCategoryForm';

export default function Create() {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">新增機具分類</h2>}
        >
            <Head title="新增機具分類" />
            <div className="py-8">
                <div className="mx-auto max-w-4xl bg-white p-6 shadow-sm sm:rounded-lg">
                    <EquipmentCategoryForm submitLabel="建立分類" />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
