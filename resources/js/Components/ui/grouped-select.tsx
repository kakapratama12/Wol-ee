import { useState, useRef, useEffect } from 'react';
import { ChevronDown } from 'lucide-react';
import { cn } from '@/lib/utils';

interface Option {
    value: string;
    label: string;
}

interface Group {
    label: string;
    options: Option[];
}

interface GroupedSelectProps {
    groups: Group[];
    value: string;
    onChange: (value: string) => void;
    placeholder?: string;
    className?: string;
}

export default function GroupedSelect({
    groups,
    value,
    onChange,
    placeholder = 'Pilih item',
    className,
}: GroupedSelectProps) {
    const [open, setOpen] = useState(false);
    const [search, setSearch] = useState('');
    const containerRef = useRef<HTMLDivElement>(null);
    const searchRef = useRef<HTMLInputElement>(null);

    // Find selected label
    const selectedLabel = groups
        .flatMap((g) => g.options)
        .find((o) => o.value === value)?.label;

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

    // Focus search on open
    useEffect(() => {
        if (open && searchRef.current) {
            searchRef.current.focus();
        }
    }, [open]);

    // Filter groups by search
    const filteredGroups = groups
        .map((g) => ({
            ...g,
            options: g.options.filter((o) =>
                o.label.toLowerCase().includes(search.toLowerCase())
            ),
        }))
        .filter((g) => g.options.length > 0);

    const handleSelect = (val: string) => {
        onChange(val);
        setOpen(false);
        setSearch('');
    };

    return (
        <div ref={containerRef} className={cn('relative', className)}>
            {/* Trigger */}
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className={cn(
                    'flex w-full items-center justify-between rounded-md border bg-white px-3 py-2 text-sm',
                    'border-gray-300 dark:border-gray-600 dark:bg-gray-800 dark:text-white',
                    open && 'ring-2 ring-blue-500'
                )}
            >
                <span className={selectedLabel ? '' : 'text-gray-400'}>
                    {selectedLabel || placeholder}
                </span>
                <ChevronDown className={cn('h-4 w-4 text-gray-400 transition-transform', open && 'rotate-180')} />
            </button>

            {/* Dropdown */}
            {open && (
                <div className="absolute z-50 mt-1 w-full rounded-md border border-gray-200 bg-white shadow-lg dark:border-gray-600 dark:bg-gray-800">
                    {/* Search */}
                    <div className="border-b border-gray-100 p-2 dark:border-gray-700">
                        <input
                            ref={searchRef}
                            type="text"
                            value={search}
                            onChange={(e) => setSearch(e.target.value)}
                            placeholder="Cari..."
                            className="w-full rounded border border-gray-200 bg-gray-50 px-2 py-1.5 text-sm outline-none focus:border-blue-400 dark:border-gray-600 dark:bg-gray-700 dark:text-white"
                        />
                    </div>

                    {/* Options — FIXED HEIGHT + SCROLL */}
                    <div className="max-h-[200px] overflow-y-auto p-1">
                        {filteredGroups.length === 0 ? (
                            <div className="px-3 py-2 text-sm text-gray-400">Tidak ditemukan</div>
                        ) : (
                            filteredGroups.map((group) => (
                                <div key={group.label}>
                                    <div className="px-2 py-1 text-xs font-semibold text-gray-400 uppercase">
                                        {group.label}
                                    </div>
                                    {group.options.map((option) => (
                                        <button
                                            key={option.value}
                                            type="button"
                                            onClick={() => handleSelect(option.value)}
                                            className={cn(
                                                'w-full rounded px-2 py-1.5 text-left text-sm hover:bg-gray-100 dark:hover:bg-gray-700',
                                                value === option.value && 'bg-blue-50 font-medium text-blue-600 dark:bg-blue-900/30 dark:text-blue-400'
                                            )}
                                        >
                                            {option.label}
                                        </button>
                                    ))}
                                </div>
                            ))
                        )}
                    </div>
                </div>
            )}
        </div>
    );
}
