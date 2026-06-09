import React, { useEffect, useState } from 'react';
import axios from 'axios';
import { Head } from '@inertiajs/react';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/money';

function Panel({ title, children, action }) {
    return (
        <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
            <div className="mb-4 flex items-center justify-between">
                <h3 className="text-base font-medium text-gray-900">{title}</h3>
                {action}
            </div>
            {children}
        </div>
    );
}

function EmptyState({ children }) {
    return (
        <p className="py-8 text-center text-sm text-gray-400">{children}</p>
    );
}

const severityStyles = {
    high: 'border-red-200 bg-red-50',
    medium: 'border-amber-200 bg-amber-50',
    low: 'border-gray-200 bg-gray-50',
};

export default function Agent() {
    const [data, setData] = useState(null);
    const [loading, setLoading] = useState(true);
    const [question, setQuestion] = useState('');
    const [asking, setAsking] = useState(false);
    const [answer, setAnswer] = useState(null);
    const [generating, setGenerating] = useState(false);

    const fetchData = () => {
        setLoading(true);
        axios
            .get('/dashboard/agent')
            .then((res) => setData(res.data))
            .catch((error) =>
                console.error('Error fetching agent data:', error),
            )
            .finally(() => setLoading(false));
    };

    useEffect(() => {
        fetchData();
    }, []);

    const handleAsk = (e) => {
        e.preventDefault();
        if (!question.trim()) return;

        setAsking(true);
        setAnswer(null);

        axios
            .post('/agent/ask', { question })
            .then((res) => setAnswer(res.data))
            .catch((error) =>
                setAnswer({
                    answer:
                        error.response?.data?.answer ??
                        'Sorry, something went wrong.',
                }),
            )
            .finally(() => setAsking(false));
    };

    const handleGenerateDigest = () => {
        setGenerating(true);
        axios
            .post('/agent/digest')
            .then(() => {
                setTimeout(fetchData, 2000);
            })
            .finally(() => setGenerating(false));
    };

    const digest = data?.digest;
    const recommendations = data?.recommendations ?? [];
    const anomalies = data?.anomalies ?? [];
    const renewals = data?.renewals ?? [];

    return (
        <AuthenticatedLayout
            header={
                <h2 className="text-xl font-semibold leading-tight text-gray-800">
                    Assistant
                </h2>
            }
        >
            <Head title="Assistant" />

            <div className="py-12">
                <div className="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                    <div className="overflow-hidden bg-white p-6 shadow-sm sm:rounded-lg">
                        <p className="text-sm text-gray-600">
                            Your personalized budgeting agent — monthly digests,
                            savings tips, anomaly alerts, and natural-language
                            questions about your spending.
                        </p>
                    </div>

                    <Panel
                        title="Ask a question"
                        action={
                            <span className="text-xs text-gray-400">
                                e.g. &quot;How much did I spend on groceries last
                                month?&quot;
                            </span>
                        }
                    >
                        <form onSubmit={handleAsk} className="flex gap-3">
                            <div className="flex-1">
                                <TextInput
                                    className="block w-full"
                                    value={question}
                                    onChange={(e) =>
                                        setQuestion(e.target.value)
                                    }
                                    placeholder="Ask about your spending…"
                                />
                            </div>
                            <PrimaryButton disabled={asking || !question.trim()}>
                                {asking ? 'Thinking…' : 'Ask'}
                            </PrimaryButton>
                        </form>
                        {answer && (
                            <div className="mt-4 rounded-lg border border-indigo-100 bg-indigo-50 p-4 text-sm text-indigo-900">
                                {answer.answer}
                            </div>
                        )}
                    </Panel>

                    {loading ? (
                        <div className="h-48 animate-pulse rounded-lg bg-gray-200" />
                    ) : (
                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <Panel
                                title="Monthly digest"
                                action={
                                    <SecondaryButton
                                        type="button"
                                        onClick={handleGenerateDigest}
                                        disabled={generating}
                                    >
                                        {generating
                                            ? 'Generating…'
                                            : 'Generate'}
                                    </SecondaryButton>
                                }
                            >
                                {!digest ? (
                                    <EmptyState>
                                        No digest yet. Generate one for last
                                        month&apos;s summary.
                                    </EmptyState>
                                ) : (
                                    <div>
                                        <div className="text-xs font-medium uppercase tracking-wide text-gray-400">
                                            {digest.period_start} —{' '}
                                            {digest.period_end}
                                        </div>
                                        <p className="mt-2 text-sm leading-relaxed text-gray-700">
                                            {digest.summary}
                                        </p>
                                    </div>
                                )}
                            </Panel>

                            <Panel title="Recommendations">
                                {recommendations.length === 0 ? (
                                    <EmptyState>
                                        No recommendations right now. Upload
                                        receipts and set budgets to get
                                        personalized tips.
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y divide-gray-100">
                                        {recommendations
                                            .slice(0, 6)
                                            .map((rec, i) => (
                                                <li key={i} className="py-3">
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {rec.title}
                                                    </div>
                                                    <p className="mt-0.5 text-xs text-gray-500">
                                                        {rec.description}
                                                    </p>
                                                    <p className="mt-1 text-xs font-medium text-indigo-600">
                                                        → {rec.action}
                                                    </p>
                                                </li>
                                            ))}
                                    </ul>
                                )}
                            </Panel>

                            <Panel title="Anomalies">
                                {anomalies.length === 0 ? (
                                    <EmptyState>
                                        No unusual activity detected.
                                    </EmptyState>
                                ) : (
                                    <ul className="space-y-2">
                                        {anomalies.map((item, i) => (
                                            <li
                                                key={i}
                                                className={`rounded-lg border p-3 text-sm ${severityStyles[item.severity] ?? severityStyles.low}`}
                                            >
                                                <div className="font-medium text-gray-900">
                                                    {item.title}
                                                </div>
                                                <p className="mt-0.5 text-xs text-gray-600">
                                                    {item.description}
                                                </p>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>

                            <Panel title="Upcoming billing & renewals">
                                {renewals.length === 0 ? (
                                    <EmptyState>
                                        No contracts due in the next two weeks.
                                    </EmptyState>
                                ) : (
                                    <ul className="divide-y divide-gray-100">
                                        {renewals.map((row) => (
                                            <li
                                                key={row.contract_id}
                                                className="flex items-center justify-between py-2.5"
                                            >
                                                <div>
                                                    <div className="text-sm font-medium text-gray-900">
                                                        {row.name}
                                                    </div>
                                                    <div className="text-xs text-gray-500">
                                                        {row.provider ?? '—'} ·{' '}
                                                        {row.next_billing_date}
                                                        {row.days_until_billing !=
                                                            null &&
                                                            row.days_until_billing >=
                                                                0 && (
                                                                <span className="ml-1">
                                                                    (in{' '}
                                                                    {
                                                                        row.days_until_billing
                                                                    }{' '}
                                                                    days)
                                                                </span>
                                                            )}
                                                    </div>
                                                </div>
                                                <div className="text-sm font-medium text-gray-900">
                                                    {formatCurrency(
                                                        row.amount,
                                                        row.currency,
                                                    )}
                                                </div>
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </Panel>
                        </div>
                    )}
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
