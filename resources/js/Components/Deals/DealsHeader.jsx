export default function DealsHeader({ postalCode, onChangePostalCode }) {
    return (
        <div className="flex flex-wrap items-baseline justify-between gap-2">
            <h3 className="text-lg font-medium text-gray-900">Deals near {postalCode}</h3>
            <button
                type="button"
                onClick={onChangePostalCode}
                className="text-sm text-indigo-600 hover:text-indigo-800"
            >
                Change postal code
            </button>
        </div>
    );
}
