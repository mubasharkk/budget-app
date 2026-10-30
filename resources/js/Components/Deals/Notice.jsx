const TONES = {
    muted: 'text-gray-400',
    info: 'text-gray-500',
    error: 'text-red-600',
};

export default function Notice({ tone = 'muted', children }) {
    return <p className={`py-10 text-center text-sm ${TONES[tone]}`}>{children}</p>;
}
