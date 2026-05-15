export default function InputLabel({
    value,
    className = '',
    children,
    required = false,
    ...props
}) {
    return (
        <label
            {...props}
            className={
                `block text-sm font-medium text-gray-700 ` +
                className
            }
        >
            {value ? value : children}
            {required && (
                <span className="ms-1 text-red-600" aria-hidden="true">
                    *
                </span>
            )}
        </label>
    );
}
