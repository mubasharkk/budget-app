export default function SkeletonRows({ count = 4, height = 'h-12' }) {
    return (
        <div className="flex flex-col gap-2">
            {Array.from({ length: count }, (_, index) => (
                <div key={index} className={`${height} animate-pulse rounded-lg bg-gray-200`} />
            ))}
        </div>
    );
}
