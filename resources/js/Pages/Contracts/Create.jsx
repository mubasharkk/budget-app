import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import ContractForm from './ContractForm';

export default function Create({
    providers,
    categories,
    billingCycles,
    statuses,
    currencies,
}) {
    const { data, setData, post, processing, errors } = useForm({
        name: '',
        provider_id: null,
        category_id: null,
        amount: '',
        currency: 'EUR',
        billing_cycle: 'monthly',
        billing_day: '',
        start_date: '',
        end_date: '',
        next_billing_date: '',
        status: 'active',
        auto_renew: true,
        description: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('contracts.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Add Contract
                </h2>
            }
        >
            <Head title="Add Contract" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <ContractForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            providers={providers}
                            categories={categories}
                            billingCycles={billingCycles}
                            statuses={statuses}
                            currencies={currencies}
                        />

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <CancelButton
                                variant="link"
                                href={route('contracts.index')}
                            />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Create Contract'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
