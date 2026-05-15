import { usePage } from '@inertiajs/react';

export const CAPABILITIES = {
    customers: {
        ...resourceCapabilities('crm.customers'),
        viewContact: 'crm.customers.view_contact.tenant',
    },
    projects: {
        ...resourceCapabilities('projects.projects'),
        viewAssigned: 'projects.projects.view.assigned',
        viewFinancials: 'projects.projects.view_financials.tenant',
    },
    projectChangeOrders: {
        ...resourceCapabilities('projects.change_orders'),
        submitReview: 'projects.change_orders.submit_review.tenant',
        approve: 'projects.change_orders.approve.tenant',
        confirmCustomer: 'projects.change_orders.confirm_customer.tenant',
        cancel: 'projects.change_orders.cancel.tenant',
        createQuotation: 'projects.change_orders.create_quotation.tenant',
        convertFinancialRecord:
            'projects.change_orders.convert_financial_record.tenant',
    },
    materials: resourceCapabilities('inventory.materials'),
    inventoryTransactions: resourceCapabilities(
        'inventory.inventory_transactions',
    ),
    equipmentCategories: resourceCapabilities('equipment.categories'),
    equipment: resourceCapabilities('equipment.equipment'),
    equipmentTransactions: {
        ...resourceCapabilities('equipment.transactions'),
    },
    suppliers: resourceCapabilities('purchasing.suppliers'),
    purchaseOrders: {
        ...resourceCapabilities('purchasing.purchase_orders'),
        receive: 'purchasing.purchase_orders.receive.tenant',
    },
    dispatches: {
        ...resourceCapabilities('field.dispatches'),
        viewAssigned: 'field.dispatches.view.assigned',
        viewOwn: 'field.dispatches.view.own',
    },
    attendance: {
        ...resourceCapabilities('field.attendance'),
        viewAssigned: 'field.attendance.view.assigned',
        viewOwn: 'field.attendance.view.own',
    },
    progressLogs: {
        ...resourceCapabilities('field.progress_logs'),
        viewAssigned: 'field.progress_logs.view.assigned',
        viewOwn: 'field.progress_logs.view.own',
    },
    workCrews: resourceCapabilities('field.work_crews'),
    workers: {
        ...resourceCapabilities('field.workers'),
        viewAssigned: 'field.workers.view.assigned',
        viewOwn: 'field.workers.view.own',
    },
    quotations: {
        ...resourceCapabilities('sales.quotations'),
        exportPdf: 'sales.quotations.export_pdf.tenant',
        submitReview: 'sales.quotations.submit_review.tenant',
        approve: 'sales.quotations.approve.tenant',
        reject: 'sales.quotations.reject.tenant',
        sendCustomer: 'sales.quotations.send_customer.tenant',
        confirmCustomer: 'sales.quotations.confirm_customer.tenant',
        convertProject: 'sales.quotations.convert_project.tenant',
        void: 'sales.quotations.void.tenant',
        reopen: 'sales.quotations.reopen.tenant',
    },
    quotationTemplates: resourceCapabilities('sales.quotation_templates'),
    financialRecords: {
        ...resourceCapabilities('finance.financial_records'),
        exportPdf: 'finance.financial_records.export_pdf.tenant',
    },
    activityLogs: resourceCapabilities('security.activity_logs'),
    systemSettings: resourceCapabilities('system.settings'),
    users: resourceCapabilities('security.users'),
    roles: {
        ...resourceCapabilities('security.roles'),
        assignCapabilities: 'security.roles.assign_capabilities.tenant',
    },
};

export function useAuthorization() {
    const { auth = {} } = usePage().props;

    return {
        can: (capability) => can(auth, capability),
        canAny: (capabilities) => canAny(auth, capabilities),
        canViewProjectFinancials: () =>
            can(auth, CAPABILITIES.projects.viewFinancials),
    };
}

export function can(auth, capability) {
    return (auth.capabilities ?? []).includes(capability);
}

export function canAny(auth, capabilities) {
    return (Array.isArray(capabilities) ? capabilities : [capabilities]).some(
        (capability) => can(auth, capability),
    );
}

function resourceCapabilities(resource) {
    return {
        view: `${resource}.view.tenant`,
        create: `${resource}.create.tenant`,
        update: `${resource}.update.tenant`,
        delete: `${resource}.delete.tenant`,
    };
}
