import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { Link, useForm } from '@inertiajs/react';

const emptyCustomer = {
    name: '',
    phone: '',
    line_id: '',
    tax_id: '',
    source: '',
    address: '',
    note: '',
    primary_contact: {
        name: '',
        title: '',
        phone: '',
        email: '',
        line_id: '',
    },
};

export default function CustomerForm({ customer = null, submitLabel }) {
    const { data, setData, post, patch, processing, errors } = useForm({
        ...emptyCustomer,
        ...customer,
        primary_contact: {
            ...emptyCustomer.primary_contact,
            ...(customer?.primary_contact ?? {}),
        },
    });

    const submit = (event) => {
        event.preventDefault();

        if (customer?.id) {
            patch(route('customers.update', customer.id));
            return;
        }

        post(route('customers.store'));
    };

    const setContactData = (field, value) => {
        setData('primary_contact', {
            ...data.primary_contact,
            [field]: value,
        });
    };

    return (
        <form onSubmit={submit} className="space-y-8">
            <section className="space-y-5">
                <div>
                    <h3 className="text-base font-semibold text-gray-950">
                        客戶資料
                    </h3>
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="name" value="客戶名稱" required />
                        <TextInput
                            id="name"
                            className="mt-1 block w-full"
                            value={data.name}
                            onChange={(event) =>
                                setData('name', event.target.value)
                            }
                            required
                            autoFocus
                        />
                        <InputError message={errors.name} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="phone" value="電話" />
                        <TextInput
                            id="phone"
                            className="mt-1 block w-full"
                            value={data.phone ?? ''}
                            onChange={(event) =>
                                setData('phone', event.target.value)
                            }
                        />
                        <InputError message={errors.phone} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="line_id" value="LINE ID" />
                        <TextInput
                            id="line_id"
                            className="mt-1 block w-full"
                            value={data.line_id ?? ''}
                            onChange={(event) =>
                                setData('line_id', event.target.value)
                            }
                        />
                        <InputError
                            message={errors.line_id}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="tax_id" value="統一編號" />
                        <TextInput
                            id="tax_id"
                            className="mt-1 block w-full"
                            value={data.tax_id ?? ''}
                            onChange={(event) =>
                                setData('tax_id', event.target.value)
                            }
                        />
                        <InputError message={errors.tax_id} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="source" value="客戶來源" />
                        <TextInput
                            id="source"
                            className="mt-1 block w-full"
                            value={data.source ?? ''}
                            onChange={(event) =>
                                setData('source', event.target.value)
                            }
                        />
                        <InputError message={errors.source} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="address" value="地址" />
                        <TextInput
                            id="address"
                            className="mt-1 block w-full"
                            value={data.address ?? ''}
                            onChange={(event) =>
                                setData('address', event.target.value)
                            }
                        />
                        <InputError
                            message={errors.address}
                            className="mt-2"
                        />
                    </div>
                </div>

                <div>
                    <InputLabel htmlFor="note" value="備註" />
                    <textarea
                        id="note"
                        className="mt-1 block min-h-28 w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                        value={data.note ?? ''}
                        onChange={(event) =>
                            setData('note', event.target.value)
                        }
                    />
                    <InputError message={errors.note} className="mt-2" />
                </div>
            </section>

            <section className="space-y-5">
                <div>
                    <h3 className="text-base font-semibold text-gray-950">
                        主要聯絡人
                    </h3>
                </div>

                <div className="grid gap-5 md:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="primary_contact_name"
                            value="聯絡人姓名"
                        />
                        <TextInput
                            id="primary_contact_name"
                            className="mt-1 block w-full"
                            value={data.primary_contact?.name ?? ''}
                            onChange={(event) =>
                                setContactData('name', event.target.value)
                            }
                        />
                        <InputError
                            message={errors['primary_contact.name']}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="primary_contact_title"
                            value="職稱"
                        />
                        <TextInput
                            id="primary_contact_title"
                            className="mt-1 block w-full"
                            value={data.primary_contact?.title ?? ''}
                            onChange={(event) =>
                                setContactData('title', event.target.value)
                            }
                        />
                        <InputError
                            message={errors['primary_contact.title']}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="primary_contact_phone"
                            value="聯絡人電話"
                        />
                        <TextInput
                            id="primary_contact_phone"
                            className="mt-1 block w-full"
                            value={data.primary_contact?.phone ?? ''}
                            onChange={(event) =>
                                setContactData('phone', event.target.value)
                            }
                        />
                        <InputError
                            message={errors['primary_contact.phone']}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="primary_contact_email"
                            value="Email"
                        />
                        <TextInput
                            id="primary_contact_email"
                            type="email"
                            className="mt-1 block w-full"
                            value={data.primary_contact?.email ?? ''}
                            onChange={(event) =>
                                setContactData('email', event.target.value)
                            }
                        />
                        <InputError
                            message={errors['primary_contact.email']}
                            className="mt-2"
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="primary_contact_line_id"
                            value="聯絡人 LINE ID"
                        />
                        <TextInput
                            id="primary_contact_line_id"
                            className="mt-1 block w-full"
                            value={data.primary_contact?.line_id ?? ''}
                            onChange={(event) =>
                                setContactData('line_id', event.target.value)
                            }
                        />
                        <InputError
                            message={errors['primary_contact.line_id']}
                            className="mt-2"
                        />
                    </div>
                </div>
            </section>

            <div className="flex items-center justify-end gap-3 border-t border-gray-200 pt-6">
                <Link href={route('customers.index')}>
                    <SecondaryButton type="button">取消</SecondaryButton>
                </Link>
                <PrimaryButton disabled={processing}>{submitLabel}</PrimaryButton>
            </div>
        </form>
    );
}
