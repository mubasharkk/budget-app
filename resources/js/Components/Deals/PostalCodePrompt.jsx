import PostalCodeForm from '@/Components/PostalCodeForm';

export default function PostalCodePrompt({ onSaved, onCancel }) {
    return (
        <section className="flex flex-col gap-4">
            <div>
                <h3 className="text-base font-medium text-gray-900">Supermarket deals near you</h3>
                <p className="mt-1 text-sm text-gray-600">
                    Offers at Lidl, Aldi, Netto, Kaufland and Edeka differ by region. Enter
                    your postal code to see this week's deals.
                </p>
            </div>
            <PostalCodeForm isFocused onSaved={onSaved} onCancel={onCancel} />
        </section>
    );
}
