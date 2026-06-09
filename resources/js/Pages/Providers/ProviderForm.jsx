import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import InputError from '@/Components/InputError';

const textareaClasses =
    'mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500';

export default function ProviderForm({ data, setData, errors }) {
    return (
        <div className="space-y-6">
            <div>
                <InputLabel htmlFor="name" value="Name" />
                <TextInput
                    id="name"
                    className="mt-1 block w-full"
                    value={data.name}
                    onChange={(e) => setData('name', e.target.value)}
                    isFocused
                    placeholder="e.g. Vodafone, Netflix, landlord"
                />
                <InputError message={errors.name} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="website" value="Website" />
                <TextInput
                    id="website"
                    type="url"
                    className="mt-1 block w-full"
                    value={data.website ?? ''}
                    onChange={(e) => setData('website', e.target.value)}
                    placeholder="https://…"
                />
                <InputError message={errors.website} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="contact_email" value="Contact email" />
                <TextInput
                    id="contact_email"
                    type="email"
                    className="mt-1 block w-full"
                    value={data.contact_email ?? ''}
                    onChange={(e) => setData('contact_email', e.target.value)}
                />
                <InputError message={errors.contact_email} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="contact_phone" value="Contact phone" />
                <TextInput
                    id="contact_phone"
                    className="mt-1 block w-full"
                    value={data.contact_phone ?? ''}
                    onChange={(e) => setData('contact_phone', e.target.value)}
                />
                <InputError message={errors.contact_phone} className="mt-2" />
            </div>

            <div>
                <InputLabel htmlFor="notes" value="Notes" />
                <textarea
                    id="notes"
                    rows={3}
                    className={textareaClasses}
                    value={data.notes ?? ''}
                    onChange={(e) => setData('notes', e.target.value)}
                />
                <InputError message={errors.notes} className="mt-2" />
            </div>
        </div>
    );
}
