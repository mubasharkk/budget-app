import { Head, useForm } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import LinkedTasks from '@/Components/Tasks/LinkedTasks';
import PrimaryButton from '@/Components/PrimaryButton';
import CancelButton from '@/Components/CancelButton';
import SavingForm from './SavingForm';

export default function Edit({
    saving,
    currencies,
    tasks,
    priorities,
    repeatOptions,
    linkTypes,
}) {
    const { data, setData, put, processing, errors } = useForm({
        amount: saving.amount,
        currency: saving.currency,
        saved_on: saving.saved_on?.slice(0, 10) ?? '',
        source: saving.source ?? '',
        notes: saving.notes ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        put(route('savings.update', saving.id));
    };

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Edit Savings
                </h2>
            }
        >
            <Head title="Edit Savings" />

            <div className="py-12">
                <div className="mx-auto max-w-3xl space-y-6 sm:px-6 lg:px-8">
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
                                {processing ? 'Saving…' : 'Save Changes'}
                            </PrimaryButton>
                        </div>
                    </form>

                    <LinkedTasks
                        tasks={tasks}
                        link={{ type: 'saving', id: saving.id }}
                        priorities={priorities}
                        repeatOptions={repeatOptions}
                        linkTypes={linkTypes}
                    />
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
