import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import ExpenseForm from './ExpenseForm';

export default function Create({ expenseTypes, currencies }) {
    const { data, setData, post, processing, errors } = useForm({
        amount: '',
        currency: 'EUR',
        spent_on: new Date().toISOString().slice(0, 10),
        description: '',
        expense_type: 'personal',
        notes: '',
        document: null,
    });

    const submit = (e) => {
        e.preventDefault();
        post(route('expenses.store'), { forceFormData: true });
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Add One-time Expense
                </h2>
            }
        >
            <Head title="Add Expense" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <ExpenseForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            expenseTypes={expenseTypes}
                            currencies={currencies}
                        />

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <CancelButton
                                variant="link"
                                href={route('expenses.index')}
                            />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Record Expense'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
