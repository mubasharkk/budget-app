import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import IncomeForm from './IncomeForm';

export default function Create({ incomeTypes, currencies }) {
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        currency: 'EUR',
        received_on: new Date().toISOString().slice(0, 10),
        source: '',
        income_type: null,
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('incomes.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Add One-time Income
                </h2>
            }
        >
            <Head title="Add Income" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <IncomeForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            incomeTypes={incomeTypes}
                            currencies={currencies}
                        />

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <CancelButton
                                variant="link"
                                href={route('incomes.index')}
                            />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Record Income'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
