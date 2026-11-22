import { useEffect, useId, useLayoutEffect, useRef, useState } from 'react';

const TYPE_STYLES = {
    category: 'bg-indigo-100 text-indigo-800',
    contract: 'bg-pink-100 text-pink-800',
    receipt: 'bg-green-100 text-green-800',
};

const SEARCH_DELAY_MS = 200;

const normalise = (text) => text.normalize('NFD').replace(/\p{Diacritic}/gu, '').toLowerCase();

/**
 * The "@word" the caret is at the end of, unless it is an already picked mention.
 */
function findTrigger(text, caret, mentions) {
    const match = /(?:^|\s)@([^\s@]*)$/.exec(text.slice(0, caret));

    if (!match) {
        return null;
    }

    const start = caret - match[1].length - 1;

    return mentions.some((mention) => start >= mention.start && start < mention.end)
        ? null
        : { start, end: caret, query: match[1] };
}

/**
 * Move mentions along with an edit and drop those whose text was edited. The
 * caret marks where the edit ends, which tells repeated characters apart.
 */
function moveMentions(before, after, caret, mentions) {
    const editEnd = before.length - (after.length - caret);
    let editStart = 0;

    while (editStart < Math.min(caret, editEnd) && before[editStart] === after[editStart]) {
        editStart++;
    }

    const shift = after.length - before.length;

    return mentions.flatMap((mention) => {
        if (mention.end <= editStart) {
            return [mention];
        }

        return mention.start >= editEnd
            ? [{ ...mention, start: mention.start + shift, end: mention.end + shift }]
            : [];
    });
}

/**
 * A text input with @-mentions. `value` is the text, `mentions` the picked
 * options with their position in it, in text order. `options` are filtered
 * here; `search` looks up further options for what was typed after the "@".
 */
export default function MentionInput({ value, mentions, onChange, options, search, label, placeholder }) {
    const inputRef = useRef(null);
    const listRef = useRef(null);
    const caretAfterPick = useRef(null);
    const listId = useId();
    const [trigger, setTrigger] = useState(null);
    const [found, setFound] = useState([]);
    const [active, setActive] = useState(0);
    const query = trigger?.query;

    useEffect(() => {
        setFound([]);
        setActive(0);

        if (!query) {
            return undefined;
        }

        let stale = false;
        const timer = setTimeout(() => {
            search(query)
                .then((results) => !stale && setFound(results))
                .catch(() => {});
        }, SEARCH_DELAY_MS);

        return () => {
            stale = true;
            clearTimeout(timer);
        };
    }, [query]);

    useLayoutEffect(() => {
        if (caretAfterPick.current !== null) {
            inputRef.current.focus();
            inputRef.current.setSelectionRange(caretAfterPick.current, caretAfterPick.current);
            caretAfterPick.current = null;
        }
    }, [value]);

    useEffect(() => {
        if (!value) {
            setTrigger(null);
        }
    }, [value]);

    const suggestions = trigger
        ? [...options.filter((option) => normalise(option.display).includes(normalise(query))), ...found]
        : [];
    const open = suggestions.length > 0;

    useEffect(() => {
        listRef.current?.children[active]?.scrollIntoView({ block: 'nearest' });
    }, [active]);

    const change = (event) => {
        const { value: text, selectionStart: caret } = event.target;
        const moved = moveMentions(value, text, caret, mentions);

        onChange(text, moved);
        setTrigger(findTrigger(text, caret, moved));
    };

    const pick = (option) => {
        const token = `@${option.display}`;
        const text = `${value.slice(0, trigger.start)}${token} ${value.slice(trigger.end)}`;
        const caret = trigger.start + token.length + 1;
        const picked = { ...option, start: trigger.start, end: trigger.start + token.length };

        caretAfterPick.current = caret;
        onChange(text, [...moveMentions(value, text, caret, mentions), picked].sort((a, b) => a.start - b.start));
        setTrigger(null);
    };

    const handleKeyDown = (event) => {
        if (!open || event.nativeEvent.isComposing) {
            return;
        }

        if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            event.preventDefault();
            const step = event.key === 'ArrowDown' ? 1 : -1;
            setActive((index) => (index + step + suggestions.length) % suggestions.length);
        } else if (event.key === 'Enter' || event.key === 'Tab') {
            event.preventDefault();
            pick(suggestions[active]);
        } else if (event.key === 'Escape') {
            event.preventDefault();
            setTrigger(null);
        }
    };

    return (
        <div>
            <input
                ref={inputRef}
                type="text"
                role="combobox"
                aria-label={label}
                aria-autocomplete="list"
                aria-expanded={open}
                aria-controls={open ? listId : undefined}
                aria-activedescendant={open ? `${listId}-${active}` : undefined}
                autoComplete="off"
                value={value}
                placeholder={placeholder}
                onChange={change}
                onKeyDown={handleKeyDown}
                onClick={(event) => setTrigger(findTrigger(value, event.target.selectionStart, mentions))}
                onBlur={() => setTrigger(null)}
                className="block w-full rounded-md border-gray-300 text-sm shadow-sm focus:border-indigo-500 focus:ring-indigo-500"
            />

            {open && (
                <ul
                    ref={listRef}
                    id={listId}
                    role="listbox"
                    aria-label="Suggestions"
                    className="mt-1 max-h-60 overflow-y-auto rounded-md border border-gray-200 bg-white text-sm shadow-sm"
                >
                    {suggestions.map((option, index) => (
                        <li
                            key={option.id}
                            id={`${listId}-${index}`}
                            role="option"
                            aria-selected={index === active}
                            onMouseDown={(event) => {
                                event.preventDefault();
                                pick(option);
                            }}
                            onMouseEnter={() => setActive(index)}
                            className={`flex cursor-pointer items-center justify-between gap-3 px-3 py-1.5 ${
                                index === active ? 'bg-indigo-50' : ''
                            }`}
                        >
                            <span className="truncate text-gray-900">{option.display}</span>
                            <span className={`rounded px-1.5 py-0.5 text-xs ${TYPE_STYLES[option.type]}`}>
                                {option.type}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {mentions.length > 0 && (
                <div className="mt-1.5 flex flex-wrap gap-1.5">
                    {mentions.map((mention) => (
                        <span key={mention.start} className={`rounded px-1.5 py-0.5 text-xs ${TYPE_STYLES[mention.type]}`}>
                            @{mention.display}
                        </span>
                    ))}
                </div>
            )}
        </div>
    );
}
