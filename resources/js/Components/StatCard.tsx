import { ReactNode } from 'react';
import { Card, CardContent } from '@/Components/ui/card';
import { cn } from '@/lib/utils';

interface StatCardProps {
    label: string;
    value: string;
    hint?: string;
    icon?: ReactNode;
    accent?: 'default' | 'success' | 'warning' | 'danger';
}

const accentMap = {
    default: 'text-primary bg-primary/10',
    success: 'text-success bg-success/10',
    warning: 'text-warning bg-warning/10',
    danger: 'text-destructive bg-destructive/10',
};

export default function StatCard({ label, value, hint, icon, accent = 'default' }: StatCardProps) {
    return (
        <Card>
            <CardContent className="flex items-center gap-4 p-5">
                {icon && (
                    <div className={cn('flex h-11 w-11 items-center justify-center rounded-lg', accentMap[accent])}>
                        {icon}
                    </div>
                )}
                <div className="min-w-0">
                    <p className="truncate text-sm text-muted-foreground">{label}</p>
                    <p
                        className={cn(
                            'text-xl font-bold tracking-tight',
                            accent === 'danger' && 'text-destructive',
                            accent === 'success' && 'text-success',
                        )}
                    >
                        {value}
                    </p>
                    {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
                </div>
            </CardContent>
        </Card>
    );
}
