import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';

export default function DateRangeFilter({ range, onChange, onApply, onClear }) {
    return (
        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <InputLabel htmlFor="start" value="From" />
                <TextInput
                    id="start"
                    type="date"
                    className="mt-1 block w-full"
                    value={range.start_date}
                    onChange={(event) => onChange({ ...range, start_date: event.target.value })}
                />
            </div>
            <div>
                <InputLabel htmlFor="end" value="To" />
                <TextInput
                    id="end"
                    type="date"
                    className="mt-1 block w-full"
                    value={range.end_date}
                    onChange={(event) => onChange({ ...range, end_date: event.target.value })}
                />
            </div>
            <div className="flex items-end gap-2">
                <PrimaryButton type="button" onClick={onApply}>
                    Apply
                </PrimaryButton>
                <SecondaryButton type="button" onClick={onClear}>
                    Clear
                </SecondaryButton>
            </div>
        </div>
    );
}
