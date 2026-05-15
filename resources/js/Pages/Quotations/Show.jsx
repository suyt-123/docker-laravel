import DangerButton from '@/Components/DangerButton';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { CAPABILITIES, useAuthorization } from '@/lib/authorization';
import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';

const confirmationStatuses = {
    not_sent: '未送客戶',
    pending: '待客戶確認',
    accepted: '客戶已接受',
    rejected: '客戶已退回',
};

export default function Show({ quotation, statuses }) {
    const { flash = {} } = usePage().props;
    const [attachmentFile, setAttachmentFile] = useState(null);
    const [attachmentDescription, setAttachmentDescription] = useState('');
    const { can } = useAuthorization();
    const canUpdate = can(CAPABILITIES.quotations.update);
    const canDelete = can(CAPABILITIES.quotations.delete);
    const canExportPdf = can(CAPABILITIES.quotations.exportPdf);
    const canSubmitReview = can(CAPABILITIES.quotations.submitReview);
    const canApprove = can(CAPABILITIES.quotations.approve);
    const canReject = can(CAPABILITIES.quotations.reject);
    const canSendCustomer = can(CAPABILITIES.quotations.sendCustomer);
    const canConfirmCustomer = can(CAPABILITIES.quotations.confirmCustomer);
    const canConvertProject = can(CAPABILITIES.quotations.convertProject);
    const canVoid = can(CAPABILITIES.quotations.void);
    const canReopen = can(CAPABILITIES.quotations.reopen);
    const isDraft = quotation.status === 'draft';
    const isReviewing = quotation.status === 'reviewing';
    const isApproved = quotation.status === 'approved';
    const isSent = quotation.status === 'sent';
    const isAccepted = quotation.status === 'accepted';
    const isVoided = quotation.status === 'voided';
    const canEditQuotation = canUpdate && isDraft;
    const canDeleteQuotation = canDelete && isDraft;

    const destroyQuotation = () => {
        if (!window.confirm(`確定要刪除「${quotation.quotation_no}」嗎？`)) {
            return;
        }

        router.delete(route('quotations.destroy', quotation.id));
    };

    const submitReview = () => {
        router.post(route('quotations.submit-review', quotation.id));
    };

    const approveQuotation = () => {
        router.post(route('quotations.approve', quotation.id));
    };

    const rejectQuotation = () => {
        router.post(route('quotations.reject', quotation.id));
    };

    const convertProject = () => {
        router.post(route('quotations.convert-project', quotation.id));
    };

    const sendCustomer = () => {
        router.post(route('quotations.send-customer', quotation.id));
    };

    const acceptCustomer = () => {
        const confirmedBy = window.prompt('客戶確認人姓名（可留空）', '');
        if (confirmedBy === null) return;

        router.post(route('quotations.accept-customer', quotation.id), {
            customer_confirmed_by_name: confirmedBy,
        });
    };

    const declineCustomer = () => {
        if (!window.confirm('確定要標記為客戶退回嗎？')) return;

        router.post(route('quotations.decline-customer', quotation.id));
    };

    const voidQuotation = () => {
        const reason = window.prompt('作廢原因（可留空）', '');
        if (reason === null) return;

        router.post(route('quotations.void', quotation.id), {
            void_reason: reason,
        });
    };

    const reopenQuotation = () => {
        if (!window.confirm('確定要重開新版報價單嗎？新版會回到草稿狀態。')) {
            return;
        }

        router.post(route('quotations.reopen', quotation.id));
    };

    const uploadAttachment = (event) => {
        event.preventDefault();
        if (!attachmentFile) return;

        router.post(
            route('quotations.attachments.store', quotation.id),
            {
                file: attachmentFile,
                description: attachmentDescription,
            },
            {
                forceFormData: true,
                onSuccess: () => {
                    setAttachmentFile(null);
                    setAttachmentDescription('');
                },
            },
        );
    };

    const deleteAttachment = (attachment) => {
        if (!window.confirm(`確定要刪除附件「${attachment.original_name}」嗎？`)) {
            return;
        }

        router.delete(route('quotations.attachments.destroy', attachment.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <h2 className="text-xl font-semibold leading-tight text-gray-800">
                            {quotation.quotation_no}
                        </h2>
                        <p className="mt-1 text-sm text-gray-500">
                            {statuses[quotation.status] ?? quotation.status}
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Link href={route('quotations.index')}>
                            <SecondaryButton type="button">
                                返回列表
                            </SecondaryButton>
                        </Link>
                        {canExportPdf && (
                            <a
                                href={route('quotations.pdf', quotation.id)}
                                rel="noreferrer"
                                target="_blank"
                            >
                                <SecondaryButton type="button">
                                    預覽 PDF
                                </SecondaryButton>
                            </a>
                        )}
                        {quotation.project && (
                            <Link href={route('projects.show', quotation.project.id)}>
                                <SecondaryButton type="button">
                                    前往工程案件
                                </SecondaryButton>
                            </Link>
                        )}
                        {canSubmitReview && isDraft && (
                            <PrimaryButton onClick={submitReview}>
                                送審
                            </PrimaryButton>
                        )}
                        {canApprove && isReviewing && (
                            <PrimaryButton onClick={approveQuotation}>
                                核准
                            </PrimaryButton>
                        )}
                        {canReject && isReviewing && (
                            <SecondaryButton type="button" onClick={rejectQuotation}>
                                退回
                            </SecondaryButton>
                        )}
                        {canSendCustomer && isApproved && (
                            <PrimaryButton onClick={sendCustomer}>
                                送客戶確認
                            </PrimaryButton>
                        )}
                        {canConfirmCustomer && (isApproved || isSent) && (
                            <>
                                <PrimaryButton onClick={acceptCustomer}>
                                    客戶接受
                                </PrimaryButton>
                                <SecondaryButton type="button" onClick={declineCustomer}>
                                    客戶退回
                                </SecondaryButton>
                            </>
                        )}
                        {canConvertProject && isAccepted && !quotation.project && (
                            <PrimaryButton onClick={convertProject}>
                                轉工程案件
                            </PrimaryButton>
                        )}
                        {canVoid && !quotation.project && !isVoided && (
                            <SecondaryButton type="button" onClick={voidQuotation}>
                                作廢
                            </SecondaryButton>
                        )}
                        {canReopen && !quotation.project && !isDraft && (
                            <SecondaryButton type="button" onClick={reopenQuotation}>
                                重開新版
                            </SecondaryButton>
                        )}
                        {canEditQuotation && (
                            <Link href={route('quotations.edit', quotation.id)}>
                                <PrimaryButton>編輯報價</PrimaryButton>
                            </Link>
                        )}
                    </div>
                </div>
            }
        >
            <Head title={quotation.quotation_no} />

            <div className="py-8">
                <div className="mx-auto max-w-7xl space-y-5 px-4 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                            {flash.success}
                        </div>
                    )}

                    <div className="grid gap-5 lg:grid-cols-3">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg lg:col-span-2">
                            <h3 className="text-base font-semibold text-gray-950">
                                報價資訊
                            </h3>
                            <dl className="mt-5 grid gap-4 sm:grid-cols-2">
                                <Info
                                    label="客戶"
                                    value={quotation.customer?.name}
                                />
                                <Info
                                    label="工程案件"
                                    value={
                                        quotation.project
                                            ? `${quotation.project.project_no} · ${quotation.project.name}`
                                            : null
                                    }
                                />
                                <Info
                                    label="建立人"
                                    value={quotation.creator?.name}
                                />
                                <Info
                                    label="核准人"
                                    value={quotation.approver?.name}
                                />
                                <Info
                                    label="有效期限"
                                    value={quotation.valid_until}
                                />
                                <Info
                                    label="客戶確認"
                                    value={
                                        confirmationStatuses[
                                            quotation.customer_confirmation_status
                                        ] ?? quotation.customer_confirmation_status
                                    }
                                />
                                <Info
                                    label="送客戶時間"
                                    value={quotation.customer_sent_at}
                                />
                                <Info
                                    label="客戶確認時間"
                                    value={quotation.customer_confirmed_at}
                                />
                                <Info
                                    label="客戶確認人"
                                    value={quotation.customer_confirmed_by_name}
                                />
                                <Info
                                    label="鎖定時間"
                                    value={quotation.locked_at}
                                />
                                <Info
                                    label="作廢時間"
                                    value={quotation.voided_at}
                                />
                                <Info
                                    label="作廢原因"
                                    value={quotation.void_reason}
                                />
                                <Info
                                    label="來源版本"
                                    value={quotation.reopened_from?.quotation_no}
                                />
                                <Info
                                    label="後續版本"
                                    value={quotation.superseded_by?.quotation_no}
                                />
                                <Info
                                    label="利潤率"
                                    value={`${quotation.profit_rate}%`}
                                />
                                <Info label="備註" value={quotation.note} />
                            </dl>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                金額
                            </h3>
                            <dl className="mt-5 space-y-4">
                                <Info
                                    label="小計"
                                    value={money(quotation.subtotal)}
                                />
                                <Info label="稅金" value={money(quotation.tax)} />
                                <Info
                                    label="折扣"
                                    value={money(quotation.discount)}
                                />
                                <Info
                                    label="總額"
                                    value={money(quotation.total)}
                                />
                            </dl>
                        </section>
                    </div>

                    <section className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                        <div className="border-b border-gray-200 p-6">
                            <h3 className="text-base font-semibold text-gray-950">
                                報價明細
                            </h3>
                        </div>
                        <div className="overflow-x-auto">
                            <table className="min-w-full divide-y divide-gray-200">
                                <thead className="bg-gray-50">
                                    <tr>
                                        <Header>項目</Header>
                                        <Header>規格</Header>
                                        <Header align="right">數量</Header>
                                        <Header align="right">單價</Header>
                                        <Header align="right">損耗</Header>
                                        <Header align="right">小計</Header>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-100 bg-white">
                                    {quotation.items.map((item) => (
                                        <tr key={item.id}>
                                            <td className="px-4 py-4 text-sm font-medium text-gray-950">
                                                {item.name}
                                            </td>
                                            <td className="px-4 py-4 text-sm text-gray-700">
                                                {item.spec || '未填'}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {number(item.quantity)}{' '}
                                                {item.unit}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {money(item.unit_price)}
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm text-gray-700">
                                                {item.waste_rate}%
                                            </td>
                                            <td className="px-4 py-4 text-right text-sm font-medium text-gray-950">
                                                {money(item.subtotal)}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </section>

                    <div className="grid gap-5 lg:grid-cols-2">
                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                PDF 版本紀錄
                            </h3>
                            <div className="mt-4 space-y-3">
                                {quotation.document_versions?.length > 0 ? (
                                    quotation.document_versions.map((version) => (
                                        <div
                                            key={version.id}
                                            className="rounded-md border border-gray-200 px-4 py-3"
                                        >
                                            <div className="flex items-center justify-between gap-3">
                                                <div className="text-sm font-medium text-gray-950">
                                                    v{version.version_number} ·{' '}
                                                    {version.file_name}
                                                </div>
                                                <span className="text-xs text-gray-500">
                                                    {version.status}
                                                </span>
                                            </div>
                                            <div className="mt-1 text-xs text-gray-500">
                                                {version.generated_at || '未填'} ·{' '}
                                                {version.generator?.name || '系統'} ·{' '}
                                                {fileSize(version.size)}
                                            </div>
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-gray-500">
                                        尚無 PDF 匯出版本。
                                    </p>
                                )}
                            </div>
                        </section>

                        <section className="bg-white p-6 shadow-sm sm:rounded-lg">
                            <h3 className="text-base font-semibold text-gray-950">
                                合約 / 客戶確認附件
                            </h3>
                            {canUpdate && !isVoided && (
                                <form
                                    onSubmit={uploadAttachment}
                                    className="mt-4 space-y-3 rounded-md border border-gray-200 p-4"
                                >
                                    <input
                                        type="file"
                                        onChange={(event) =>
                                            setAttachmentFile(event.target.files?.[0] ?? null)
                                        }
                                        className="block w-full text-sm text-gray-700"
                                    />
                                    <input
                                        type="text"
                                        value={attachmentDescription}
                                        onChange={(event) =>
                                            setAttachmentDescription(event.target.value)
                                        }
                                        placeholder="附件說明"
                                        className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                                    />
                                    <PrimaryButton disabled={!attachmentFile}>
                                        上傳附件
                                    </PrimaryButton>
                                </form>
                            )}
                            <div className="mt-4 space-y-3">
                                {quotation.attachments?.length > 0 ? (
                                    quotation.attachments.map((attachment) => (
                                        <div
                                            key={attachment.id}
                                            className="flex items-center justify-between gap-3 rounded-md border border-gray-200 px-4 py-3"
                                        >
                                            <div>
                                                <a
                                                    href={attachment.url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="text-sm font-medium text-indigo-700 hover:text-indigo-900"
                                                >
                                                    {attachment.original_name}
                                                </a>
                                                <div className="mt-1 text-xs text-gray-500">
                                                    {attachment.description || '無說明'} ·{' '}
                                                    {attachment.uploader?.name || '系統'} ·{' '}
                                                    {fileSize(attachment.size)}
                                                </div>
                                            </div>
                                            {canUpdate && !isVoided && (
                                                <button
                                                    type="button"
                                                    onClick={() => deleteAttachment(attachment)}
                                                    className="text-sm font-medium text-red-600 hover:text-red-800"
                                                >
                                                    刪除
                                                </button>
                                            )}
                                        </div>
                                    ))
                                ) : (
                                    <p className="text-sm text-gray-500">
                                        尚無附件。
                                    </p>
                                )}
                            </div>
                        </section>
                    </div>

                    {canDeleteQuotation && (
                        <div className="flex justify-end">
                            <DangerButton onClick={destroyQuotation}>
                                刪除報價單
                            </DangerButton>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Header({ children, align = 'left' }) {
    return (
        <th
            className={[
                'px-4 py-3 text-xs font-semibold uppercase tracking-wide text-gray-500',
                align === 'right' ? 'text-right' : 'text-left',
            ].join(' ')}
        >
            {children}
        </th>
    );
}

function Info({ label, value }) {
    return (
        <div>
            <dt className="text-sm font-medium text-gray-500">{label}</dt>
            <dd className="mt-1 whitespace-pre-line text-sm text-gray-950">
                {value || '未填'}
            </dd>
        </div>
    );
}

function money(value) {
    return `NT$ ${Number(value ?? 0).toLocaleString()}`;
}

function number(value) {
    return Number(value ?? 0).toLocaleString();
}

function fileSize(value) {
    const size = Number(value ?? 0);
    if (size >= 1024 * 1024) {
        return `${(size / 1024 / 1024).toFixed(1)} MB`;
    }

    if (size >= 1024) {
        return `${(size / 1024).toFixed(1)} KB`;
    }

    return `${size} B`;
}
