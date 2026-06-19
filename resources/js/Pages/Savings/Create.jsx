import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import SavingForm from './SavingForm';

export default function Create({ currencies }) {
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        currency: 'EUR',
        saved_on: new Date().toISOString().slice(0, 10),
        source: '',
        notes: '',
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('savings.store'));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Add Savings
                </h2>
            }
        >
            <Head title="Add Savings" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <SavingForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            currencies={currencies}
                        />

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <CancelButton
                                variant="link"
                                href={route('savings.index')}
                            />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Record Savings'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
