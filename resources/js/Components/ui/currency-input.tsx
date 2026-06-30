import * as React from 'react';
import { cn } from '@/lib/utils';

interface CurrencyInputProps extends Omit<
    React.InputHTMLAttributes<HTMLInputElement>,
    'onChange' | 'value'
> {
    value: number | string;
    onChange: (rawValue: string) => void;
    /** Prefix shown before the formatted value (default: "Rp") */
    prefix?: string;
}

/**
 * CurrencyInput — auto-formats number with thousand separators (ID locale).
 * Displays "Rp 100.000" but passes back raw "100000" via onChange.
 */
const CurrencyInput = React.forwardRef<HTMLInputElement, CurrencyInputProps>(
    ({ className, value, onChange, prefix = 'Rp', ...props }, ref) => {
        const [display, setDisplay] = React.useState('');

        // Sync external value → display
        React.useEffect(() => {
            const raw =
                typeof value === 'string' ? value.replace(/[^\d]/g, '') : String(value ?? '');
            if (raw === '') {
                setDisplay('');
            } else {
                const num = parseInt(raw, 10);
                setDisplay(Number.isFinite(num) ? num.toLocaleString('id-ID') : '');
            }
        }, [value]);

        const handleChange = (e: React.ChangeEvent<HTMLInputElement>) => {
            const raw = e.target.value.replace(/[^\d]/g, '');
            onChange(raw);
        };

        return (
            <div className={cn('relative flex items-center', className)}>
                <span className="pointer-events-none absolute left-3 text-sm text-muted-foreground select-none">
                    {prefix}
                </span>
                <input
                    ref={ref}
                    type="text"
                    inputMode="numeric"
                    className={cn(
                        'flex h-10 w-full rounded-md border border-input bg-background pl-12 pr-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50 tabular-nums',
                    )}
                    value={display}
                    onChange={handleChange}
                    {...props}
                />
            </div>
        );
    },
);
CurrencyInput.displayName = 'CurrencyInput';

export { CurrencyInput };
