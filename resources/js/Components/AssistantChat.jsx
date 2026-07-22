import React, { useEffect, useRef, useState } from 'react';
import axios from 'axios';
import { MentionsInput, Mention } from 'react-mentions';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { formatCurrency } from '@/utils/money';

const mentionsInputStyle = {
    control: { fontSize: 14, fontWeight: 'normal' },
    '&singleLine': {
        display: 'block',
        highlighter: {
            padding: '8px 12px',
            border: '1px solid transparent',
        },
        input: {
            padding: '8px 12px',
            border: '1px solid #d1d5db',
            borderRadius: 6,
            outline: 'none',
        },
    },
    suggestions: {
        zIndex: 50,
        list: {
            backgroundColor: 'white',
            border: '1px solid #e5e7eb',
            borderRadius: 6,
            fontSize: 14,
            boxShadow: '0 4px 12px rgba(0,0,0,0.08)',
            overflow: 'hidden',
            maxHeight: 240,
            overflowY: 'auto',
        },
        item: {
            padding: '6px 12px',
            '&focused': { backgroundColor: '#eef2ff' },
        },
    },
};

const mentionStyle = {
    backgroundColor: '#e0e7ff',
    borderRadius: 4,
    padding: '1px 0',
};

const parseMentions = (mentions) =>
    mentions
        .map((m) => {
            const [type, id] = String(m.id).split(':');
            return { type, id: Number(id) };
        })
        .filter((m) => m.type && Number.isInteger(m.id));

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
    const [value, setValue] = useState('');
    const [plainText, setPlainText] = useState('');
    const [mentionState, setMentionState] = useState([]);
    const [categories, setCategories] = useState([]);
    const [asking, setAsking] = useState(false);
    const [loading, setLoading] = useState(true);
    const [portalHost, setPortalHost] = useState(null);
    const endRef = useRef(null);

    useEffect(() => {
        setPortalHost(document.body);
    }, []);

    useEffect(() => {
        axios
            .get(route('agent.history'))
            .then((res) => setMessages(res.data.messages))
            .catch(() => {})
            .finally(() => setLoading(false));

        axios
            .get(route('agent.mentionables'))
            .then((res) => setCategories(res.data.categories))
            .catch(() => {});
    }, []);

    useEffect(() => {
        endRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages, asking]);

    const send = (e) => {
        e.preventDefault();
        const q = plainText.trim();
        if (!q || asking) {
            return;
        }

        const mentions = parseMentions(mentionState);

        setMessages((m) => [
            ...m,
            { id: `tmp-${Date.now()}`, role: 'user', content: q },
        ]);
        setValue('');
        setPlainText('');
        setMentionState([]);
        setAsking(true);

        axios
            .post(route('agent.ask'), { question: q, mentions })
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
                className="flex items-start gap-3 border-t border-gray-100 px-6 py-4"
            >
                <div className="flex-1">
                    <MentionsInput
                        value={value}
                        onChange={(e, newValue, newPlainText, mentions) => {
                            setValue(newValue);
                            setPlainText(newPlainText);
                            setMentionState(mentions);
                        }}
                        onKeyDown={(e) => {
                            if (e.key === 'Enter' && !e.shiftKey) {
                                e.preventDefault();
                                send(e);
                            }
                        }}
                        singleLine
                        allowSuggestionsAboveCursor
                        suggestionsPortalHost={portalHost}
                        placeholder="Ask about your spending… use @ to mention a category"
                        style={mentionsInputStyle}
                        a11ySuggestionsListLabel="Suggested categories"
                    >
                        <Mention
                            trigger="@"
                            data={categories}
                            markup="@[__display__](__id__)"
                            displayTransform={(id, display) => `@${display}`}
                            appendSpaceOnAdd
                            style={mentionStyle}
                        />
                    </MentionsInput>
                </div>
                <PrimaryButton disabled={asking || !plainText.trim()}>
                    Ask
                </PrimaryButton>
            </form>
        </div>
    );
}
