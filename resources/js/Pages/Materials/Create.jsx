import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import MaterialForm from './Partials/MaterialForm';

export default function Create({ categories, units }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增材料
                </h2>
            }
        >
            <Head title="新增材料" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <MaterialForm
                            categories={categories}
                            units={units}
                            submitLabel="建立材料"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
