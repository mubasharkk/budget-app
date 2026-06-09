import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import ContractForm from './ContractForm';

const toDateInput = (value) => (value ? value.slice(0, 10) : '');

export default function Edit({
    contract,
    providers,
    categories,
    billingCycles,
    statuses,
    currencies,
}) {
    const { data, setData, put, processing, errors } = useForm({
        name: contract.name ?? '',
        provider_id: contract.provider_id ?? null,
        category_id: contract.category_id ?? null,
        amount: contract.amount ?? '',
        currency: contract.currency ?? 'EUR',
        billing_cycle: contract.billing_cycle ?? 'monthly',
        billing_day: contract.billing_day ?? '',
        start_date: toDateInput(contract.start_date),
        end_date: toDateInput(contract.end_date),
        next_billing_date: toDateInput(contract.next_billing_date),
        status: contract.status ?? 'active',
        auto_renew: !!contract.auto_renew,
        description: contract.description ?? '',
        notes: contract.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('contracts.update', contract.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Contract
                </h2>
            }
        >
            <Head title="Edit Contract" />

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
                                {processing ? 'Saving…' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
