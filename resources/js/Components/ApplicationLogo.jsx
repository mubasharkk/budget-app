export default function ApplicationLogo({ className = 'h-9 w-9', ...props }) {
    return (
        <img
            src="/images/logo.png"
            alt={`${import.meta.env.VITE_APP_NAME ?? 'Budget App'} logo`}
            className={`object-contain ${className}`}
            {...props}
        />
    );
}
