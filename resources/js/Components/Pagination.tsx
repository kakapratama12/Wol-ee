import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

export default function Pagination({ links }: { links: PaginationLink[] }) {
    if (links.length <= 3) return null;

    return (
        <div className="flex flex-wrap items-center justify-end gap-1 p-4">
            {links.map((link, i) =>
                link.url ? (
                    <Link
                        key={i}
                        href={link.url}
                        className={cn(
                            'rounded-md px-3 py-1.5 text-sm',
                            link.active ? 'bg-primary text-primary-foreground' : 'hover:bg-accent',
                        )}
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ) : (
                    <span
                        key={i}
                        className="rounded-md px-3 py-1.5 text-sm text-muted-foreground"
                        dangerouslySetInnerHTML={{ __html: link.label }}
                    />
                ),
            )}
        </div>
    );
}
