import { useState, useRef, useEffect, useMemo } from 'react';
import { ChevronDown, Plus, Search, X } from 'lucide-react';
import { cn } from '@/lib/utils';

export interface ComboboxOption {
    id: string | number;
    label: string;
    sublabel?: string;
}

interface CreatableComboboxProps {
    options: ComboboxOption[];
    value: string | number;
    onChange: (value: string) => void;
    onCreateNew: () => void;
    placeholder?: string;
    searchPlaceholder?: string;
    createLabel?: string;
    className?: string;
    error?: string;
}

export default function CreatableCombobox({
    options,
    value,
    onChange,
    onCreateNew,
    placeholder = '- Pilih -',
    searchPlaceholder = 'Cari...',
    createLabel = 'Buat Baru',
    className,
    error,
}: CreatableComboboxProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const inputRef = useRef<HTMLInputElement>(null);

    const selected = options.find((o) => String(o.id) === String(value));

    const filtered = useMemo(() => {
        if (!search) return options;
        const q = search.toLowerCase();
        return options.filter(
            (o) =>
                o.label.toLowerCase().includes(q) ||
                (o.sublabel && o.sublabel.toLowerCase().includes(q)),
        );
    }, [options, search]);

    // Close on outside click
    useEffect(() => {
        const handler = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
                setSearch('');
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    // Focus input when opened
    useEffect(() => {
        if (open) {
            setTimeout(() => inputRef.current?.focus(), 50);
        }
    }, [open]);

    const handleSelect = (id: string | number) => {
        onChange(String(id));
        setOpen(false);
        setSearch('');
    };

    const handleClear = (e: React.MouseEvent) => {
        e.stopPropagation();
        onChange('');
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            {/* Trigger */}
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className={cn(
                    'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                    open && 'ring-2 ring-ring ring-offset-2',
                    error && 'border-destructive',
                )}
            >
                <span className={cn('truncate', !selected && 'text-muted-foreground')}>
                    {selected ? (
                        <span>
                            {selected.label}
                            {selected.sublabel && (
                                <span className="ml-1 text-muted-foreground">
                                    ({selected.sublabel})
                                </span>
                            )}
                        </span>
                    ) : (
                        placeholder
                    )}
                </span>
                <span className="flex items-center gap-1">
                    {selected && (
                        <span
                            role="button"
                            tabIndex={-1}
                            onClick={handleClear}
                            className="rounded-sm p-0.5 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-3 w-3" />
                        </span>
                    )}
                    <ChevronDown
                        className={cn(
                            'h-4 w-4 text-muted-foreground transition-transform',
                            open && 'rotate-180',
                        )}
                    />
                </span>
            </button>

            {error && <p className="mt-1 text-xs text-destructive">{error}</p>}

            {/* Dropdown */}
            {open && (
                <div className="absolute z-50 mt-1 w-full overflow-hidden rounded-md border border-border bg-popover shadow-md">
                    {/* Search input */}
                    <div className="flex items-center gap-2 border-b border-border px-3">
                        <Search className="h-4 w-4 shrink-0 text-muted-foreground" />
                        <input
                            ref={inputRef}
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder={searchPlaceholder}
                            className="h-9 w-full bg-transparent text-sm outline-none placeholder:text-muted-foreground"
                        />
                    </div>

                    {/* Options list */}
                    <div className="max-h-60 overflow-y-auto p-1">
                        {filtered.length === 0 && (
                            <p className="px-3 py-2 text-sm text-muted-foreground">
                                Tidak ditemukan
                            </p>
                        )}
                        {filtered.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                onClick={() => handleSelect(option.id)}
                                className={cn(
                                    'flex w-full items-center justify-between rounded-sm px-3 py-2 text-sm outline-none hover:bg-accent hover:text-accent-foreground',
                                    String(option.id) === String(value) && 'bg-accent font-medium',
                                )}
                            >
                                <span className="truncate">
                                    {option.label}
                                    {option.sublabel && (
                                        <span className="ml-1 text-muted-foreground">
                                            ({option.sublabel})
                                        </span>
                                    )}
                                </span>
                            </button>
                        ))}
                    </div>

                    {/* Create new button */}
                    <div className="border-t border-border p-1">
                        <button
                            type="button"
                            onClick={() => {
                                setOpen(false);
                                setSearch('');
                                onCreateNew();
                            }}
                            className="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm text-primary hover:bg-accent hover:text-accent-foreground outline-none"
                        >
                            <Plus className="h-4 w-4" />
                            {createLabel}
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}
