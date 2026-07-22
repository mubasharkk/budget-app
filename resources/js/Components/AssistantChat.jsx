import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import { formatCurrency } from '@/utils/money';

function ItemResults({ data }) {
    if (!data?.items?.length) {
        return null;
    }

    return (
        <div className="mt-2 overflow-x-auto">
            <table className="min-w-full text-xs">
                <thead>
                    <tr className="text-left text-gray-500">
                        <th className="pb-1 pr-4 font-medium">Item</th>
                        <th className="pb-1 px-2 text-right font-medium">Qty</th>
                        <th className="pb-1 px-2 text-right font-medium">Times</th>
                        <th className="pb-1 pl-2 text-right font-medium">Spend</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-gray-200">
                    {data.items.map((item, i) => (
                        <tr key={i}>
                            <td className="py-1 pr-4 text-gray-900">
                                {item.name}
                                {item.category && (
                                    <span className="text-gray-400">
                                        {' '}· {item.category}
                                    </span>
                                )}
                            </td>
                            <td className="py-1 px-2 text-right text-gray-600">
                                {item.quantity}
                            </td>
                            <td className="py-1 px-2 text-right text-gray-600">
                                {item.purchases}
                            </td>
                            <td className="py-1 pl-2 text-right font-medium text-gray-900">
                                {formatCurrency(item.spend)}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

function CategoryResults({ data }) {
    if (!data?.categories?.length) {
        return null;
    }

    return (
        <ul className="mt-2 space-y-1 text-xs">
            {data.categories.map((c, i) => (
                <li key={i} className="flex items-center justify-between gap-4">
                    <span className="text-gray-900">
                        {c.category}
                        {!c.is_parent && (
                            <span className="text-gray-400"> (sub)</span>
                        )}
                    </span>
                    <span className="whitespace-nowrap font-medium text-gray-900">
                        {formatCurrency(c.spend)} · {c.item_count} items
                    </span>
                </li>
            ))}
        </ul>
    );
}

function ResultBlock({ data }) {
    if (!data) {
        return null;
    }
    if (data.intent === 'item_search') {
        return <ItemResults data={data} />;
    }
    if (data.intent === 'category_search') {
        return <CategoryResults data={data} />;
    }
    return null;
}

export default function AssistantChat() {
    const [messages, setMessages] = useState([]);
    const [question, setQuestion] = useState('');
    const [asking, setAsking] = useState(false);
    const [loading, setLoading] = useState(true);
    const endRef = useRef(null);

    useEffect(() => {
        axios
            .get(route('agent.history'))
            .then((res) => setMessages(res.data.messages))
            .catch(() => {})
            .finally(() => setLoading(false));
    }, []);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, asking]);

    const send = (e) => {
        e.preventDefault();
        const q = question.trim();
        if (!q || asking) {
            return;
        }

        setMessages((m) => [
            ...m,
            { id: `tmp-${Date.now()}`, role: 'user', content: q },
        ]);
        setQuestion('');
        setAsking(true);

        axios
            .post(route('agent.ask'), { question: q })
            .then((res) =>
                setMessages((m) => [
                    ...m,
                    {
                        id: `a-${Date.now()}`,
                        role: 'assistant',
                        content: res.data.answer,
                        data: res.data.data,
                    },
                ]),
            )
            .catch((error) =>
                setMessages((m) => [
                    ...m,
                    {
                        id: `e-${Date.now()}`,
                        role: 'assistant',
                        content:
                            error.response?.data?.answer ??
                            'Sorry, something went wrong. Try rephrasing.',
                    },
                ]),
            )
            .finally(() => setAsking(false));
    };

    const newChat = () => {
        axios
            .delete(route('agent.history.clear'))
            .then(() => setMessages([]))
            .catch(() => {});
    };

    return (
        <div className="overflow-hidden bg-white shadow-sm sm:rounded-lg">
            <div className="flex items-center justify-between border-b border-gray-100 px-6 py-4">
                <div>
                    <h3 className="text-base font-medium text-gray-900">
                        Ask about your items
                    </h3>
                    <p className="text-xs text-gray-400">
                        e.g. “how many eggs did I buy last month?”
                    </p>
                </div>
                <SecondaryButton
                    type="button"
                    onClick={newChat}
                    disabled={messages.length === 0}
                >
                    New chat
                </SecondaryButton>
            </div>

            <div className="max-h-96 space-y-4 overflow-y-auto px-6 py-4">
                {loading ? (
                    <div className="h-16 animate-pulse rounded-lg bg-gray-100" />
                ) : messages.length === 0 ? (
                    <p className="py-8 text-center text-sm text-gray-400">
                        Ask a question to search your receipts by item or
                        category.
                    </p>
                ) : (
                    messages.map((m) => (
                        <div
                            key={m.id}
                            className={
                                m.role === 'user'
                                    ? 'flex justify-end'
                                    : 'flex justify-start'
                            }
                        >
                            <div
                                className={
                                    m.role === 'user'
                                        ? 'max-w-lg rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white'
                                        : 'max-w-lg rounded-lg bg-gray-50 px-4 py-2 text-sm text-gray-800'
                                }
                            >
                                <div>{m.content}</div>
                                {m.role === 'assistant' && (
                                    <ResultBlock data={m.data} />
                                )}
                            </div>
                        </div>
                    ))
                )}
                {asking && (
                    <div className="flex justify-start">
                        <div className="max-w-lg rounded-lg bg-gray-50 px-4 py-2 text-sm text-gray-400">
                            Thinking…
                        </div>
                    </div>
                )}
                <div ref={endRef} />
            </div>

            <form
                onSubmit={send}
                className="flex gap-3 border-t border-gray-100 px-6 py-4"
            >
                <TextInput
                    className="block w-full"
                    value={question}
                    onChange={(e) => setQuestion(e.target.value)}
                    placeholder="Ask about your spending…"
                />
                <PrimaryButton disabled={asking || !question.trim()}>
                    Ask
                </PrimaryButton>
            </form>
        </div>
    );
}
