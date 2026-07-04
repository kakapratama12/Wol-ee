import { ReactNode } from 'react';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';

export interface Column<T> {
    /** Header label (desktop table) */
    header: string;
    /** Cell content renderer */
    render: (row: T) => ReactNode;
    /** Hidden on mobile card view? Default: false */
    hideOnMobile?: boolean;
    /** Render as primary bold text on mobile card */
    primary?: boolean;
    /** Render as secondary text on mobile card */
    secondary?: boolean;
    /** Render as amount (large, right-aligned) on mobile card */
    amount?: boolean;
    /** Render as badge/tag on mobile card */
    badge?: boolean;
}

interface Props<T> {
    data: T[];
    columns: Column<T>[];
    /** Actions column (edit/delete buttons) */
    actions?: (row: T) => ReactNode;
    /** Empty state message */
    emptyMessage?: string;
    /** Unique key extractor */
    keyFn: (row: T) => string | number;
}

/**
 * Responsive table: desktop = normal table, mobile = stacked cards.
 * Columns with `primary`, `secondary`, or `amount` props control card layout.
 * Columns with `hideOnMobile: true` are hidden on mobile.
 */
export function MobileCardTable<T>({
    data,
    columns,
    actions,
    emptyMessage = 'Belum ada data.',
    keyFn,
}: Props<T>) {
    if (data.length === 0) {
        return (
            <div className="py-12 text-center text-sm text-muted-foreground">
                {emptyMessage}
            </div>
        );
    }

    return (
        <>
            {/* Desktop: normal table */}
            <div className="hidden md:block">
                <Table>
                    <TableHeader>
                        <TableRow>
                            {columns.map((col) => (
                                <TableHead key={col.header}>{col.header}</TableHead>
                            ))}
                            {actions && <TableHead className="text-right" />}
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {data.map((row) => (
                            <TableRow key={keyFn(row)}>
                                {columns.map((col) => (
                                    <TableCell key={col.header}>
                                        {col.render(row)}
                                    </TableCell>
                                ))}
                                {actions && (
                                    <TableCell className="text-right">
                                        {actions(row)}
                                    </TableCell>
                                )}
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
            </div>

            {/* Mobile: card view */}
            <div className="md:hidden divide-y">
                {data.map((row) => (
                    <div key={keyFn(row)} className="px-4 py-3 space-y-1.5">
                        {/* Primary + Amount row */}
                        <div className="flex items-start justify-between gap-2">
                            <div className="min-w-0 flex-1">
                                {columns
                                    .filter((c) => c.primary)
                                    .map((col) => (
                                        <div
                                            key={col.header}
                                            className="font-medium text-sm truncate"
                                        >
                                            {col.render(row)}
                                        </div>
                                    ))}
                                {columns
                                    .filter((c) => c.secondary)
                                    .map((col) => (
                                        <div
                                            key={col.header}
                                            className="text-xs text-muted-foreground truncate"
                                        >
                                            {col.render(row)}
                                        </div>
                                    ))}
                            </div>
                            <div className="text-right shrink-0">
                                {columns
                                    .filter((c) => c.amount)
                                    .map((col) => (
                                        <div
                                            key={col.header}
                                            className="font-medium text-sm"
                                        >
                                            {col.render(row)}
                                        </div>
                                    ))}
                            </div>
                        </div>

                        {/* Badge row */}
                        {columns
                            .filter((c) => c.badge && !c.hideOnMobile)
                            .length > 0 && (
                            <div className="flex flex-wrap gap-1.5">
                                {columns
                                    .filter((c) => c.badge && !c.hideOnMobile)
                                    .map((col) => (
                                        <span
                                            key={col.header}
                                            className="text-xs text-muted-foreground"
                                        >
                                            {col.render(row)}
                                        </span>
                                    ))}
                            </div>
                        )}

                        {/* Action buttons */}
                        {actions && (
                            <div className="flex justify-end gap-1 pt-1">
                                {actions(row)}
                            </div>
                        )}
                    </div>
                ))}
            </div>
        </>
    );
}
