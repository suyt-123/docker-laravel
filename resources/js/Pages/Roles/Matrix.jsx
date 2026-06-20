import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Check, Minus } from 'lucide-react';
import { Head, Link } from '@inertiajs/react';

export default function Matrix({ roles, actions, matrix, specialCapabilities }) {
    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:flex-wrap sm:items-center sm:justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        權限矩陣
                    </h2>
                    <Link href={route('roles.index')}>
                        <SecondaryButton type="button">返回角色</SecondaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="權限矩陣" />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                    <section className="bg-white p-5 shadow-sm sm:rounded-lg">
                        <div className="flex flex-col gap-1">
                            <h3 className="text-base font-semibold text-gray-950">
                                CRUD 權限
                            </h3>
                            <p className="text-sm text-gray-500">
                                每格依序顯示查看、 新增、 編輯、 刪除。查看權限若有限制，會標示全部、指派或本人。
                            </p>
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header sticky>功能</Header>
                                        {roles.map((role) => (
                                            <Header key={role.id}>
                                                <div className="min-w-40">
                                                    <div className="font-semibold text-gray-700">
                                                        {role.name}
                                                    </div>
                                                    <div className="mt-1 text-xs font-normal lowercase text-gray-400">
                                                        {role.code}
                                                    </div>
                                                </div>
                                            </Header>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {matrix.map((module) => (
                                        <tr key={module.key} className="align-top">
                                            <td className="sticky left-0 z-10 bg-white px-4 py-4 text-sm font-semibold text-gray-950">
                                                <div className="min-w-36">
                                                    {module.label}
                                                </div>
                                                <div className="mt-1 text-xs font-normal text-gray-400">
                                                    {module.key}
                                                </div>
                                            </td>
                                            {roles.map((role) => (
                                                <td
                                                    key={role.id}
                                                    className="px-4 py-4"
                                                >
                                                    <div className="grid min-w-40 grid-cols-2 gap-2">
                                                        {Object.keys(actions).map(
                                                            (action) => (
                                                                <ActionState
                                                                    key={action}
                                                                    state={
                                                                        module
                                                                            .roles[
                                                                            role
                                                                                .id
                                                                        ][action]
                                                                    }
                                                                />
                                                            ),
                                                        )}
                                                    </div>
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <section className="bg-white p-5 shadow-sm sm:rounded-lg">
                        <div className="flex flex-col gap-1">
                            <h3 className="text-base font-semibold text-gray-950">
                                特殊權限
                            </h3>
                            <p className="text-sm text-gray-500">
                                非 CRUD 動作，例如匯出 PDF 或指派 capability。
                            </p>
                        </div>

                        <div className="mt-5 overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header sticky>Capability</Header>
                                        {roles.map((role) => (
                                            <Header key={role.id}>
                                                {role.name}
                                            </Header>
                                        ))}
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {specialCapabilities.map((capability) => (
                                        <tr key={capability.id}>
                                            <td className="sticky left-0 z-10 bg-white px-4 py-4 text-sm">
                                                <div className="min-w-64 font-medium text-gray-950">
                                                    {capability.name}
                                                </div>
                                                <div className="mt-1 text-xs text-gray-400">
                                                    {capability.code}
                                                </div>
                                            </td>
                                            {roles.map((role) => (
                                                <td
                                                    key={role.id}
                                                    className="px-4 py-4 text-center"
                                                >
                                                    <BooleanMark
                                                        granted={
                                                            capability.roles[
                                                                role.id
                                                            ]
                                                        }
                                                    />
                                                </td>
                                            ))}
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function ActionState({ state }) {
    return (
        <div
            className={[
                'rounded-md border px-2.5 py-2 text-xs',
                state.granted
                    ? 'border-emerald-200 bg-emerald-50 text-emerald-800'
                    : 'border-gray-200 bg-gray-50 text-gray-400',
            ].join(' ')}
        >
            <div className="flex items-center gap-1.5">
                <BooleanMark granted={state.granted} small />
                <span className="font-medium">{state.label}</span>
            </div>
            {state.scopes.length > 0 && (
                <div className="mt-1 flex flex-wrap gap-1">
                    {state.scopes.map((scope) => (
                        <span
                            key={scope.code}
                            className="rounded bg-white/80 px-1.5 py-0.5 text-[11px]"
                        >
                            {scope.label}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}

function BooleanMark({ granted, small = false }) {
    const size = small ? 'h-3.5 w-3.5' : 'h-5 w-5';

    if (!granted) {
        return <Minus className={`${size} text-gray-300`} />;
    }

    return <Check className={`${size} text-emerald-600`} />;
}

function Header({ children, sticky = false }) {
    return (
        <th
            className={[
                'px-4 py-3 text-left text-xs font-medium uppercase tracking-wider text-gray-500',
                sticky ? 'sticky left-0 z-20 bg-gray-50' : '',
            ].join(' ')}
        >
            {children}
        </th>
    );
}
