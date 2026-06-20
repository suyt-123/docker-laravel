import ApplicationLogo from '@/Components/ApplicationLogo';
import Dropdown from '@/Components/Dropdown';
import { Link, usePage } from '@inertiajs/react';
import {
    BadgeDollarSign,
    Boxes,
    BriefcaseBusiness,
    CalendarDays,
    ClipboardList,
    FileText,
    History,
    Hammer,
    Home,
    MapPinCheck,
    Menu,
    PackageSearch,
    ReceiptText,
    Settings,
    ShieldCheck,
    UserCog,
    Users,
    X,
} from 'lucide-react';
import { useState } from 'react';

const navigationGroups = [
    {
        label: '總覽',
        items: [
            {
                label: 'Dashboard',
                routeName: 'dashboard',
                active: 'dashboard',
                capability: 'core.dashboard.view.tenant',
                icon: Home,
            },
        ],
    },
    {
        label: '營運管理',
        items: [
            {
                label: '客戶管理',
                routeName: 'customers.index',
                active: 'customers.*',
                capability: 'crm.customers.view.tenant',
                icon: Users,
            },
            {
                label: '工程案件',
                routeName: 'projects.index',
                active: 'projects.*',
                capabilities: [
                    'projects.projects.view.tenant',
                    'projects.projects.view.assigned',
                ],
                icon: BriefcaseBusiness,
            },
            {
                label: '追加單',
                routeName: 'project-change-orders.index',
                active: 'project-change-orders.*',
                capability: 'projects.change_orders.view.tenant',
                icon: FileText,
            },
            {
                label: '報價單',
                routeName: 'quotations.index',
                active: 'quotations.*',
                capability: 'sales.quotations.view.tenant',
                icon: ReceiptText,
            },
            {
                label: '報價模板',
                routeName: 'quotation-templates.index',
                active: 'quotation-templates.*',
                capability: 'sales.quotation_templates.view.tenant',
                icon: FileText,
            },
            {
                label: '財務收款',
                routeName: 'financial-records.index',
                active: 'financial-records.*',
                capability: 'finance.financial_records.view.tenant',
                icon: BadgeDollarSign,
            },
        ],
    },
    {
        label: '現場作業',
        items: [
            {
                label: '派工管理',
                routeName: 'dispatches.index',
                active: [
                    'dispatches.index',
                    'dispatches.create',
                    'dispatches.show',
                    'dispatches.edit',
                ],
                capabilities: [
                    'field.dispatches.view.tenant',
                    'field.dispatches.view.assigned',
                    'field.dispatches.view.own',
                ],
                icon: CalendarDays,
            },
            {
                label: '排程甘特圖',
                routeName: 'dispatches.schedule',
                active: 'dispatches.schedule',
                capabilities: [
                    'field.dispatches.view.tenant',
                    'field.dispatches.view.assigned',
                    'field.dispatches.view.own',
                ],
                icon: CalendarDays,
            },
            {
                label: '工程日誌',
                routeName: 'progress-logs.index',
                active: 'progress-logs.*',
                capabilities: [
                    'field.progress_logs.view.tenant',
                    'field.progress_logs.view.assigned',
                    'field.progress_logs.view.own',
                ],
                icon: FileText,
            },
        ],
    },
    {
        label: '現場回報',
        items: [
            {
                label: 'GPS 打卡',
                routeName: 'attendance-records.index',
                active: 'attendance-records.*',
                capabilities: [
                    'field.attendance.view.tenant',
                    'field.attendance.view.assigned',
                    'field.attendance.view.own',
                ],
                icon: MapPinCheck,
            },
            {
                label: '工時報表',
                routeName: 'reports.work-hours',
                active: 'reports.work-hours',
                capabilities: [
                    'field.attendance.view.tenant',
                    'field.attendance.view.assigned',
                    'field.attendance.view.own',
                ],
                icon: ClipboardList,
            },
        ],
    },
    {
        label: '採購庫存',
        items: [
            {
                label: '採購單',
                routeName: 'purchase-orders.index',
                active: 'purchase-orders.*',
                capability: 'purchasing.purchase_orders.view.tenant',
                icon: ClipboardList,
            },
            {
                label: '供應商',
                routeName: 'suppliers.index',
                active: 'suppliers.*',
                capability: 'purchasing.suppliers.view.tenant',
                icon: Users,
            },
            {
                label: '材料管理',
                routeName: 'materials.index',
                active: 'materials.*',
                capability: 'inventory.materials.view.tenant',
                icon: Boxes,
            },
            {
                label: '庫存異動',
                routeName: 'inventory-transactions.index',
                active: 'inventory-transactions.*',
                capability: 'inventory.inventory_transactions.view.tenant',
                icon: PackageSearch,
            },
        ],
    },
    {
        label: '機具資產',
        items: [
            {
                label: '工具與機具',
                routeName: 'equipment.index',
                active: 'equipment.*',
                capability: 'equipment.equipment.view.tenant',
                icon: Hammer,
            },
            {
                label: '機具分類',
                routeName: 'equipment-categories.index',
                active: 'equipment-categories.*',
                capability: 'equipment.categories.view.tenant',
                icon: ClipboardList,
            },
            {
                label: '機具交易',
                routeName: 'equipment-transactions.index',
                active: 'equipment-transactions.*',
                capability: 'equipment.transactions.view.tenant',
                icon: History,
            },
        ],
    },
    {
        label: '人員工班',
        items: [
            {
                label: '師傅管理',
                routeName: 'workers.index',
                active: 'workers.*',
                capabilities: [
                    'field.workers.view.tenant',
                    'field.workers.view.assigned',
                    'field.workers.view.own',
                ],
                icon: Users,
            },
            {
                label: '工班管理',
                routeName: 'work-crews.index',
                active: 'work-crews.*',
                capability: 'field.work_crews.view.tenant',
                icon: Hammer,
            },
        ],
    },
    {
        label: '系統設定',
        items: [
            {
                label: '使用者管理',
                routeName: 'users.index',
                active: 'users.*',
                capability: 'security.users.view.tenant',
                icon: UserCog,
            },
            {
                label: '操作紀錄',
                routeName: 'activity-logs.index',
                active: 'activity-logs.*',
                capability: 'security.activity_logs.view.tenant',
                icon: History,
            },
            {
                label: '系統設定',
                routeName: 'system-settings.edit',
                active: 'system-settings.*',
                capability: 'system.settings.view.tenant',
                icon: Settings,
            },
            {
                label: '角色權限',
                routeName: 'roles.index',
                active: 'roles.*',
                capability: 'security.roles.view.tenant',
                icon: ShieldCheck,
            },
        ],
    },
];

export default function AuthenticatedLayout({ header, children }) {
    const auth = usePage().props.auth;
    const user = auth.user;
    const [sidebarOpen, setSidebarOpen] = useState(false);
    const can = (capability) => auth.capabilities?.includes(capability);
    const canAny = (capabilities) =>
        (Array.isArray(capabilities) ? capabilities : [capabilities]).some(can);
    const groups = navigationGroups
        .map((group) => ({
            ...group,
            items: group.items.filter((item) =>
                canAny(item.capabilities ?? item.capability),
            ),
        }))
        .filter((group) => group.items.length > 0);

    return (
        <div className="min-h-screen bg-slate-100">
            <aside className="fixed inset-y-0 left-0 z-40 hidden w-72 border-r border-slate-200 bg-white lg:flex lg:flex-col">
                <SidebarContent groups={groups} user={user} />
            </aside>

            {sidebarOpen && (
                <div className="fixed inset-0 z-50 lg:hidden">
                    <button
                        type="button"
                        aria-label="關閉選單"
                        className="absolute inset-0 bg-slate-950/40"
                        onClick={() => setSidebarOpen(false)}
                    />
                    <aside className="relative flex h-full w-80 max-w-[calc(100vw-2rem)] flex-col bg-white shadow-xl">
                        <div className="flex h-16 items-center justify-between border-b border-slate-200 px-5">
                            <Brand />
                            <button
                                type="button"
                                onClick={() => setSidebarOpen(false)}
                                className="inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900"
                                aria-label="關閉選單"
                            >
                                <X className="h-5 w-5" />
                            </button>
                        </div>
                        <SidebarNav groups={groups} onNavigate={() => setSidebarOpen(false)} />
                    </aside>
                </div>
            )}

            <div className="lg:pl-72">
                <header className="sticky top-0 z-30 border-b border-slate-200 bg-white/95 backdrop-blur">
                    <div className="flex h-16 items-center justify-between gap-4 px-4 sm:px-6 lg:px-8">
                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setSidebarOpen(true)}
                                className="inline-flex h-10 w-10 items-center justify-center rounded-md text-slate-500 hover:bg-slate-100 hover:text-slate-900 lg:hidden"
                                aria-label="開啟選單"
                            >
                                <Menu className="h-5 w-5" />
                            </button>
                            <div className="lg:hidden">
                                <Brand compact />
                            </div>
                            <div className="hidden text-sm text-slate-500 sm:block">
                                工程管理系統
                            </div>
                        </div>

                        <UserMenu user={user} />
                    </div>
                </header>

                {header && (
                    <div className="border-b border-slate-200 bg-white">
                        <div className="px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </div>
                )}

                <main>{children}</main>
            </div>
        </div>
    );
}

function SidebarContent({ groups, user }) {
    return (
        <>
            <div className="flex h-16 items-center border-b border-slate-200 px-5">
                <Brand />
            </div>
            <SidebarNav groups={groups} />
            <div className="border-t border-slate-200 p-4">
                <div className="rounded-md bg-slate-50 px-3 py-3">
                    <div className="truncate text-sm font-medium text-slate-900">
                        {user.name}
                    </div>
                    <div className="mt-0.5 truncate text-xs text-slate-500">
                        {user.email}
                    </div>
                </div>
            </div>
        </>
    );
}

function SidebarNav({ groups, onNavigate = () => {} }) {
    return (
        <nav className="flex-1 overflow-y-auto px-3 py-5">
            <div className="space-y-6">
                {groups.map((group) => (
                    <div key={group.label}>
                        <div className="px-3 text-xs font-semibold uppercase tracking-wider text-slate-400">
                            {group.label}
                        </div>
                        <div className="mt-2 space-y-1">
                            {group.items.map((item) => (
                                <SidebarItem
                                    key={item.routeName}
                                    item={item}
                                    onNavigate={onNavigate}
                                />
                            ))}
                        </div>
                    </div>
                ))}
            </div>
        </nav>
    );
}

function SidebarItem({ item, onNavigate }) {
    const Icon = item.icon;
    const active = (Array.isArray(item.active) ? item.active : [item.active]).some(
        (activeRoute) => route().current(activeRoute),
    );

    return (
        <Link
            href={route(item.routeName)}
            onClick={onNavigate}
            className={[
                'group flex items-center gap-3 rounded-md px-3 py-2.5 text-sm font-medium transition',
                active
                    ? 'bg-indigo-50 text-indigo-700'
                    : 'text-slate-600 hover:bg-slate-50 hover:text-slate-950',
            ].join(' ')}
        >
            <Icon
                className={[
                    'h-5 w-5 shrink-0',
                    active
                        ? 'text-indigo-600'
                        : 'text-slate-400 group-hover:text-slate-600',
                ].join(' ')}
            />
            <span className="truncate">{item.label}</span>
        </Link>
    );
}

function Brand({ compact = false }) {
    return (
        <Link href={route('dashboard')} className="flex items-center gap-3">
            <ApplicationLogo className="block h-9 w-auto fill-current text-slate-800" />
            {!compact && (
                <div>
                    <div className="text-sm font-semibold text-slate-950">
                        工程管理
                    </div>
                    <div className="text-xs text-slate-500">Tin House ERP</div>
                </div>
            )}
        </Link>
    );
}

function UserMenu({ user }) {
    return (
        <Dropdown>
            <Dropdown.Trigger>
                <span className="inline-flex rounded-md">
                    <button
                        type="button"
                        className="inline-flex items-center gap-2 rounded-md border border-slate-200 bg-white px-3 py-2 text-sm font-medium text-slate-600 transition hover:bg-slate-50 hover:text-slate-950 focus:outline-none"
                    >
                        <span className="flex h-7 w-7 items-center justify-center rounded-full bg-indigo-50 text-xs font-semibold text-indigo-700">
                            {user.name?.slice(0, 1).toUpperCase()}
                        </span>
                        <span className="hidden max-w-32 truncate sm:inline">
                            {user.name}
                        </span>
                        <svg
                            className="h-4 w-4 text-slate-400"
                            xmlns="http://www.w3.org/2000/svg"
                            viewBox="0 0 20 20"
                            fill="currentColor"
                        >
                            <path
                                fillRule="evenodd"
                                d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                clipRule="evenodd"
                            />
                        </svg>
                    </button>
                </span>
            </Dropdown.Trigger>

            <Dropdown.Content>
                <Dropdown.Link href={route('profile.edit')}>
                    個人資料
                </Dropdown.Link>
                <Dropdown.Link href={route('logout')} method="post" as="button">
                    登出
                </Dropdown.Link>
            </Dropdown.Content>
        </Dropdown>
    );
}
