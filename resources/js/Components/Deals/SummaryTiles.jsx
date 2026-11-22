import StatTile from '@/Components/Deals/StatTile';
import { formatCurrency } from '@/utils/money';

export default function SummaryTiles({ summary }) {
    return (
        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <StatTile label="Deals on things you buy" value={summary.match_count} />
            <StatTile
                label="Possible saving"
                value={summary.possible_saving > 0 ? formatCurrency(summary.possible_saving) : '—'}
                tone={summary.possible_saving > 0 ? 'positive' : 'default'}
            />
            <StatTile label="Best store for you" value={summary.best_retailer_label ?? '—'} />
        </div>
    );
}
