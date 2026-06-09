import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import BudgetForm from './BudgetForm';

export default function Edit({
    budget,
    categories,
    periods,
    currencies,
}) {
    const { data, setData, put, processing, errors } = useForm({
        category_id: budget.category_id,
        period: budget.period,
        amount: budget.amount,
        currency: budget.currency,
        starts_on: budget.starts_on?.slice(0, 10) ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('budgets.update', budget.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Budget
                </h2>
            }
        >
            <Head title="Edit Budget" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl sm:px-6 lg:px-8">
                    <form
                        onSubmit={submit}
                        className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg"
                    >
                        <BudgetForm
                            data={data}
                            setData={setData}
                            errors={errors}
                            categories={categories}
                            periods={periods}
                            currencies={currencies}
                        />

                        <div className="mt-6 flex items-center justify-end gap-3">
                            <CancelButton
                                variant="link"
                                href={route('budgets.index', {
                                    period: budget.period,
                                })}
                            />
                            <PrimaryButton disabled={processing}>
                                {processing ? 'Saving…' : 'Save Budget'}
                            </PrimaryButton>
                        </div>
                    </form>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
