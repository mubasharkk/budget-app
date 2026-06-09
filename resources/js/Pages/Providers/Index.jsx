import { Head, Link, router, usePage } from '@inertiajs/react';
import { useState } from 'react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import { PlusIcon, PencilSquareIcon, TrashIcon } from '@heroicons/react/24/outline';

function ProviderLogo({ provider }) {
    const [failed, setFailed] = useState(false);

    if (!provider.logo || failed) {
        return (
            <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded bg-gray-100 text-xs font-medium text-gray-500">
                {provider.name.charAt(0)}
            </div>
        );
    }

    return (
        <img
            src={provider.logo}
            alt=""
            className="h-9 w-9 shrink-0 rounded border border-gray-100 object-contain"
            onError={() => setFailed(true)}
        />
    );
}

export default function Index({ providers }) {
    const flash = usePage().props.flash ?? {};

    const handleDelete = (provider) => {
        if (window.confirm(`Delete provider "${provider.name}"?`)) {
            router.delete(route('providers.destroy', provider.id), {
                preserveScroll: true,
            });
        }
    };

    return (
        <AuthenticatedLayout
            header={
                <div className="flex items-center justify-between">
                    <h2 className="text-xl font-semibold leading-tight text-gray-800">
                        Providers
                    </h2>
                    <Link href={route('providers.create')}>
                        <PrimaryButton icon={PlusIcon}>
                            Add Provider
                        </PrimaryButton>
                    </Link>
                </div>
            }
        >
            <Head title="Providers" />

            <div className="py-12">
                <div className="mx-auto max-w-5xl space-y-6 sm:px-6 lg:px-8">
                    {flash.success && (
                        <div className="rounded-md bg-green-50 p-4 text-sm text-green-800">
                            {flash.success}
                        </div>
                    )}

                    {providers.length === 0 ? (
                        <div className="rounded-lg bg-white p-10 text-center shadow-sm">
                            <p className="text-gray-500">
                                No providers yet. Add the companies behind your
                                contracts (telecom, insurer, landlord…).
                            </p>
                            <div className="mt-4 flex justify-center">
                                <Link href={route('providers.create')}>
                                    <PrimaryButton icon={PlusIcon}>
                                        Add your first provider
                                    </PrimaryButton>
                                </Link>
                            </div>
                        </div>
                    ) : (
                        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
                            <ul className="divide-y divide-gray-100">
                                {providers.map((provider) => (
                                    <li
                                        key={provider.id}
                                        className="flex items-center justify-between px-6 py-4"
                                    >
                                        <div className="flex min-w-0 items-center gap-3">
                                            <ProviderLogo provider={provider} />
                                            <div className="min-w-0">
                                            <div className="font-medium text-gray-900">
                                                {provider.name}
                                            </div>
                                            <div className="mt-0.5 text-sm text-gray-500">
                                                {provider.website ||
                                                    provider.contact_email ||
                                                    'No contact info'}
                                                {' · '}
                                                {provider.contracts_count}{' '}
                                                contract
                                                {provider.contracts_count === 1
                                                    ? ''
                                                    : 's'}
                                            </div>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Link
                                                href={route(
                                                    'providers.edit',
                                                    provider.id,
                                                )}
                                                className="text-gray-400 hover:text-gray-700"
                                                title="Edit"
                                            >
                                                <PencilSquareIcon className="h-5 w-5" />
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleDelete(provider)
                                                }
                                                className="text-gray-400 hover:text-red-600"
                                                title="Delete"
                                            >
                                                <TrashIcon className="h-5 w-5" />
                                            </button>
                                        </div>
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
