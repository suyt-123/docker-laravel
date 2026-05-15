import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head } from '@inertiajs/react';
import ProjectForm from './Partials/ProjectForm';

export default function Create({ options, statuses, projectNo }) {
    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    新增工程案件
                </h2>
            }
        >
            <Head title="新增工程案件" />

            <div className="py-8">
                <div className="mx-auto max-w-6xl px-4 sm:px-6 lg:px-8">
                    <div className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <ProjectForm
                            options={options}
                            statuses={statuses}
                            projectNo={projectNo}
                            submitLabel="建立案件"
                        />
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
