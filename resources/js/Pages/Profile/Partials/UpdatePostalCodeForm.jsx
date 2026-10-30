import PostalCodeForm from '@/Components/PostalCodeForm';

export default function UpdatePostalCodeForm({ className = '' }) {
    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-gray-900">
                    Postal code
                </h2>
                <p className="mt-1 text-sm text-gray-600">
                    Used to show supermarket deals in your area on the Deals
                    page.
                </p>
            </header>
            <div className="mt-6">
                <PostalCodeForm />
            </div>
        </section>
    );
}
