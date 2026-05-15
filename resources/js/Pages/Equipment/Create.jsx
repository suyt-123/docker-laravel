import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import EquipmentForm from './Partials/EquipmentForm';

export default function Create({
    equipment,
    options,
    statuses,
    conditions,
}) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增工具與機具
                </h2>
            }
        >
            <Head title="新增工具與機具" />
            <div className="py-8">
                <div className="mx-auto max-w-5xl bg-white p-6 shadow-sm sm:rounded-lg">
                    <EquipmentForm
                        equipment={equipment}
                        options={options}
                        statuses={statuses}
                        conditions={conditions}
                        submitLabel="建立機具"
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
