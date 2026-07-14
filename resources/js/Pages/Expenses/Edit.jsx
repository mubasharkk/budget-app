import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import ExpenseForm from './ExpenseForm';

export default function Edit({ expense, expenseTypes, currencies }) {
    const { data, setData, put, processing, errors } = useForm({
        amount: expense.amount,
        currency: expense.currency,
        spent_on: expense.spent_on?.slice(0, 10) ?? '',
        description: expense.description ?? '',
        expense_type: expense.expense_type,
        notes: expense.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('expenses.update', expense.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Expense
                </h2>
            }
        >
            <Head title="Edit Expense" />

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
                                {processing ? 'Saving…' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
