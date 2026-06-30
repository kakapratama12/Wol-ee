import { useState, useRef, useEffect, Fragment } from 'react';
import { cn } from '@/lib/utils';

interface Option {
    id: number;
    name: string;
    sublabel?: string;
}

interface CreatableComboboxProps {
    options: Option[];
    value: number | string;
    onChange: (value: number | string) => void;
    placeholder?: string;
    /** Called when user creates a new item. Receives name, should return new option via promise. */
    onCreate?: (name: string) => Promise<Option>;
    className?: string;
    disabled?: boolean;
}

export default function CreatableCombobox({
    options,
    value,
    onChange,
    placeholder = 'Pilih atau ketik untuk buat baru...',
    onCreate,
    className,
    disabled = false,
}: CreatableComboboxProps) {
    const [open, setOpen] = useState(false);
    const [query, setQuery] = useState('');
    const [creating, setCreating] = useState(false);
    const inputRef = useRef<HTMLInputElement>(null);
    const containerRef = useRef<HTMLDivElement>(null);

    const selected = options.find((o) => o.id === Number(value));

    const filtered = options.filter((o) => o.name.toLowerCase().includes(query.toLowerCase()));

    const exactMatch = options.some((o) => o.name.toLowerCase() === query.toLowerCase());

    const showCreate = query.length > 0 && !exactMatch && !!onCreate;

    useEffect(() => {
        const handleClickOutside = (e: MouseEvent) => {
            if (containerRef.current && !containerRef.current.contains(e.target as Node)) {
                setOpen(false);
                setQuery('');
            }
        };
        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleCreate = async () => {
        if (!onCreate || !query.trim()) return;
        setCreating(true);
        try {
            const newOption = await onCreate(query.trim());
            onChange(newOption.id);
            setQuery('');
            setOpen(false);
        } catch {
            // error handled by parent
        } finally {
            setCreating(false);
        }
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            <button
                type="button"
                disabled={disabled}
                onClick={() => {
                    setOpen(true);
                    setTimeout(() => inputRef.current?.focus(), 0);
                }}
                className={cn(
                    'flex h-10 w-full items-center justify-between rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background placeholder:text-muted-foreground focus:outline-none focus:ring-2 focus:ring-ring focus:ring-offset-2 disabled:cursor-not-allowed disabled:opacity-50',
                    !selected && 'text-muted-foreground',
                )}
            >
                <span className="truncate">{selected ? selected.name : placeholder}</span>
                <svg
                    className="ml-2 h-4 w-4 shrink-0 opacity-50"
                    fill="none"
                    stroke="currentColor"
                    viewBox="0 0 24 24"
                >
                    <path
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        strokeWidth={2}
                        d="M19 9l-7 7-7-7"
                    />
                </svg>
            </button>

            {open && (
                <div className="absolute z-50 mt-1 max-h-60 w-full overflow-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md">
                    <input
                        ref={inputRef}
                        type="text"
                        value={query}
                        onChange={(e) => setQuery(e.target.value)}
                        placeholder="Ketik untuk cari..."
                        className="flex h-9 w-full rounded-md border border-input bg-transparent px-3 py-1 text-sm placeholder:text-muted-foreground focus:outline-none"
                    />
                    <div className="mt-1">
                        {filtered.length === 0 && !showCreate && (
                            <div className="px-3 py-2 text-sm text-muted-foreground">
                                Tidak ditemukan.
                            </div>
                        )}
                        {filtered.map((option) => (
                            <button
                                key={option.id}
                                type="button"
                                onClick={() => {
                                    onChange(option.id);
                                    setOpen(false);
                                    setQuery('');
                                }}
                                className={cn(
                                    'flex w-full items-center justify-between rounded-sm px-3 py-2 text-sm hover:bg-accent hover:text-accent-foreground',
                                    option.id === Number(value) && 'bg-accent',
                                )}
                            >
                                <span className="truncate">{option.name}</span>
                                {option.sublabel && (
                                    <span className="ml-2 text-xs text-muted-foreground">
                                        {option.sublabel}
                                    </span>
                                )}
                            </button>
                        ))}
                        {showCreate && (
                            <button
                                type="button"
                                disabled={creating}
                                onClick={handleCreate}
                                className="flex w-full items-center gap-2 rounded-sm px-3 py-2 text-sm text-primary hover:bg-accent"
                            >
                                {creating ? (
                                    <span>Menyimpan...</span>
                                ) : (
                                    <>
                                        <svg
                                            className="h-4 w-4"
                                            fill="none"
                                            stroke="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path
                                                strokeLinecap="round"
                                                strokeLinejoin="round"
                                                strokeWidth={2}
                                                d="M12 4v16m8-8H4"
                                            />
                                        </svg>
                                        <span>Buat "{query}"</span>
                                    </>
                                )}
                            </button>
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
