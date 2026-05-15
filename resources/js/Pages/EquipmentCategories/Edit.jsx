import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import EquipmentCategoryForm from './Partials/EquipmentCategoryForm';

export default function Edit({ category }) {
    return (
        <AuthenticatedLayout
            header={<h2 className="text-xl font-semibold leading-tight text-gray-800">編輯機具分類</h2>}
        >
            <Head title={`編輯機具分類 - ${category.name}`} />
            <div className="py-8">
                <div className="mx-auto max-w-4xl bg-white p-6 shadow-sm sm:rounded-lg">
                    <EquipmentCategoryForm
                        category={category}
                        submitLabel="儲存分類"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
