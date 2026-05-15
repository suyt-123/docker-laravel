import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link } from '@inertiajs/react';

export default function Show({ category }) {
    const { can } = useAuthorization();

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        {category.name}
                    </h2>
                    <div className="flex gap-2">
                        <Link href={route('equipment-categories.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {can(CAPABILITIES.equipmentCategories.update) && (
                            <Link href={route('equipment-categories.edit', category.id)}>
                                <PrimaryButton>編輯分類</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={category.name} />
            <div className="py-8">
                <div className="mx-auto max-w-5xl space-y-5 px-4 sm:px-6 lg:px-8">
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <dl className="grid gap-4 sm:grid-cols-2">
                            <Info label="代碼" value={category.code} />
                            <Info label="狀態" value={category.is_active ? '啟用' : '停用'} />
                            <Info label="說明" value={category.description} wide />
                        </dl>
                    </section>
                    <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                        <h3 className="text-base font-semibold text-gray-950">
                            此分類設備
                        </h3>
                        <div className="mt-4 space-y-3">
                            {category.equipment.length === 0 && (
                                <p className="text-sm text-gray-500">
                                    目前沒有設備
                                </p>
                            )}
                            {category.equipment.map((item) => (
                                <Link
                                    key={item.id}
                                    href={route('equipment.show', item.id)}
                                    className="block rounded-md border border-gray-200 p-3 hover:border-indigo-300"
                                >
                                    <div className="font-medium text-gray-950">
                                        {item.equipment_no} · {item.name}
                                    </div>
                                    <div className="mt-1 text-sm text-gray-500">
                                        {item.status} / {item.condition}
                                    </div>
                                </Link>
                            ))}
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Info({ label, value, wide = false }) {
    return (
        <div className={wide ? 'sm:col-span-2' : ''}>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
        </div>
    );
}
