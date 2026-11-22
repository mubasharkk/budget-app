import { useEffect, useState } from 'react';
import axios from 'axios';
import { SparklesIcon } from '@heroicons/react/24/outline';
import PrimaryButton from '@/Components/PrimaryButton';

const dateFormat = new Intl.DateTimeFormat(undefined, { day: 'numeric', month: 'short', year: 'numeric' });
const formatDate = (value) => dateFormat.format(new Date(`${String(value).slice(0, 10)}T00:00:00`));
const dateRange = (analysis) => `${formatDate(analysis.period_start)} – ${formatDate(analysis.period_end)}`;
const periodLabel = (analysis) =>
    analysis.period_label ? `${analysis.period_label} (${dateRange(analysis)})` : dateRange(analysis);

/**
 * An AI-written summary of exactly the period and category the page shows.
 * Past summaries are kept and can be reopened.
 */
export default function ConsumptionAnalysisCard({ filters, appliedWindow, spend }) {
    const [analyses, setAnalyses] = useState([]);
    const [selectedId, setSelectedId] = useState(null);
    const [running, setRunning] = useState(false);
    const [error, setError] = useState(null);

    const isShown = (analysis) =>
        analysis.period_label === appliedWindow.label &&
        analysis.period_start === appliedWindow.start &&
        analysis.period_end === appliedWindow.end;
    const forPage = analyses.find(isShown);
    const selected = analyses.find((analysis) => analysis.id === selectedId) ?? forPage;
    const isOutdated = selected && isShown(selected) && selected.spend !== null && Math.abs(selected.spend - spend) >= 0.01;

    useEffect(() => {
        setSelectedId(null);
        setError(null);
    }, [appliedWindow.label, appliedWindow.start, appliedWindow.end]);

    useEffect(() => {
        axios
            .get(route('insights.analyses.index'))
            .then((res) => setAnalyses(res.data.analyses))
            .catch(() => setError('Earlier summaries could not be loaded.'));
    }, []);

    const analyse = () => {
        setRunning(true);
        setError(null);
        axios
            .post(route('insights.analyses.store'), filters)
            .then((res) => {
                setAnalyses((current) => [res.data.analysis, ...current]);
                setSelectedId(res.data.analysis.id);
            })
            .catch((err) =>
                setError(
                    err.response?.status === 429
                        ? 'Too many analyses at once — wait a minute and try again.'
                        : (err.response?.data?.message ?? 'The analysis could not be written. Please try again.'),
                ),
            )
            .finally(() => setRunning(false));
    };

    return (
        <section className="overflow-hidden bg-white p-4 shadow-sm sm:p-6 sm:rounded-lg">
            <div className="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h3 className="flex items-center gap-2 text-base font-medium text-gray-900">
                        <SparklesIcon className="h-5 w-5 text-indigo-500" />
                        AI summary
                    </h3>
                    <p className="text-sm text-gray-600">
                        Puts the figures above for {appliedWindow.label} into words, with tips.
                    </p>
                </div>
                <PrimaryButton type="button" onClick={analyse} disabled={running || spend === 0}>
                    {running ? 'Writing…' : forPage ? 'Write again' : 'Write summary'}
                </PrimaryButton>
            </div>

            {analyses.length > 0 && (
                <select
                    value={selected?.id ?? ''}
                    onChange={(e) => setSelectedId(Number(e.target.value))}
                    className="mt-4 max-w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
                    aria-label="Earlier summaries"
                >
                    {!selected && <option value="">Earlier summaries…</option>}
                    {analyses.map((analysis) => (
                        <option key={analysis.id} value={analysis.id}>
                            {periodLabel(analysis)} · made {formatDate(analysis.created_at)}
                        </option>
                    ))}
                </select>
            )}

            {isOutdated && !running && (
                <p className="mt-3 rounded-md bg-amber-50 p-3 text-sm text-amber-900">
                    Your figures have changed since this was written. Write it again to bring it up to date.
                </p>
            )}

            {error && <p className="mt-4 rounded-md bg-red-50 p-3 text-sm text-red-700">{error}</p>}

            {running && (
                <div className="mt-4 space-y-2" aria-live="polite">
                    <p className="text-sm text-gray-500">Writing your analysis — this can take up to a minute.</p>
                    <div className="h-4 w-3/4 animate-pulse rounded bg-gray-200" />
                    <div className="h-4 w-1/2 animate-pulse rounded bg-gray-200" />
                </div>
            )}

            {!running && !selected && !error && (
                <p className="mt-4 text-sm text-gray-600">
                    No summary for this period yet.
                </p>
            )}

            {!running && selected && (
                <div className="mt-4 space-y-4">
                    <p className="text-xs font-medium uppercase tracking-wider text-gray-500">{periodLabel(selected)}</p>
                    <p className="text-sm leading-6 text-gray-800">{selected.summary}</p>
                    {selected.findings.length > 0 && (
                        <ul className="grid grid-cols-1 gap-3 md:grid-cols-2">
                            {selected.findings.map((finding) => (
                                <li key={finding.title} className="rounded-md border border-gray-100 bg-gray-50 p-3">
                                    <div className="text-sm font-medium text-gray-900">{finding.title}</div>
                                    <div className="mt-1 text-sm text-gray-600">{finding.detail}</div>
                                </li>
                            ))}
                        </ul>
                    )}
                    {selected.tips.length > 0 && (
                        <div>
                            <div className="text-sm font-medium text-gray-900">Tips</div>
                            <ul className="mt-1 list-disc space-y-1 pl-5 text-sm text-gray-600">
                                {selected.tips.map((tip) => (
                                    <li key={tip}>{tip}</li>
                                ))}
                            </ul>
                        </div>
                    )}
                </div>
            )}
        </section>
    );
}
