import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { useForm, usePage } from '@inertiajs/react';
import { CheckIcon } from '@heroicons/react/24/outline';

export default function PostalCodeForm({ onSaved, onCancel, isFocused = false }) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing } = useForm({
        postal_code: user.postal_code ?? '',
    });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.postal-code.update'), {
            preserveScroll: true,
            onSuccess: () => onSaved?.(),
        });
    };

    return (
        <form onSubmit={submit} className="flex flex-col gap-2">
            <InputLabel htmlFor="postal_code" value="Postal code" />
            <div className="flex flex-wrap items-start gap-2">
                <TextInput
                    id="postal_code"
                    className="block w-32"
                    value={data.postal_code}
                    onChange={(e) => setData('postal_code', e.target.value)}
                    inputMode="numeric"
                    maxLength={5}
                    placeholder="10115"
                    autoComplete="postal-code"
                    isFocused={isFocused}
                    required
                />
                <PrimaryButton icon={CheckIcon} disabled={processing}>
                    Save
                </PrimaryButton>
                {onCancel && (
                    <SecondaryButton type="button" onClick={onCancel}>
                        Cancel
                    </SecondaryButton>
                )}
            </div>
            <InputError message={errors.postal_code} />
        </form>
    );
}
