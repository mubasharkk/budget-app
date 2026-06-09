import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import ProviderForm from './ProviderForm';

export default function Edit({ provider }) {
    const { data, setData, put, processing, errors } = useForm({
        name: provider.name ?? '',
        logo: provider.logo ?? '',
        website: provider.website ?? '',
        contact_email: provider.contact_email ?? '',
        contact_phone: provider.contact_phone ?? '',
        notes: provider.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('providers.update', provider.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Provider
                </h2>
            }
        >
            <Head title="Edit Provider" />

            <div className="py-12">
                <div className="mx-auto max-w-2xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <ProviderForm
                            data={data}
                            setData={setData}
                            errors={errors}
                        />

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <CancelButton
                                variant="link"
                                href={route('providers.index')}
                            />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
