const DAY = 24 * 60 * 60 * 1000;

/**
 * A "YYYY-MM-DD" due date as a local Date, so it isn't shifted by the time zone.
 */
const parseDate = (value) => {
    const [year, month, day] = value.split('-').map(Number);

    return new Date(year, month - 1, day);
};

const startOfToday = () => {
    const now = new Date();

    return new Date(now.getFullYear(), now.getMonth(), now.getDate());
};

export const daysUntil = (value) =>
    Math.round((parseDate(value) - startOfToday()) / DAY);

export const formatDueDate = (value) =>
    parseDate(value).toLocaleDateString('de-DE', {
        day: 'numeric',
        month: 'short',
        year: 'numeric',
    });

export const dueLabel = (value) => {
    const days = daysUntil(value);

    if (days < -1) {
        return `${-days} days overdue`;
    }

    if (days === -1) {
        return 'Yesterday';
    }

    if (days === 0) {
        return 'Today';
    }

    if (days === 1) {
        return 'Tomorrow';
    }

    return days <= 7 ? `In ${days} days` : formatDueDate(value);
};

export const dueTone = (value, done = false) => {
    const days = daysUntil(value);

    if (done || days > 7) {
        return 'bg-gray-100 text-gray-600';
    }

    if (days < 0) {
        return 'bg-red-100 text-red-700';
    }

    return days <= 1 ? 'bg-amber-100 text-amber-800' : 'bg-blue-50 text-blue-700';
};
