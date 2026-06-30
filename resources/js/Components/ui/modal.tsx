import { PropsWithChildren, useEffect } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    size?: 'default' | 'lg' | 'xl';
    className?: string;
}

export default function Modal({ open, onClose, title, size = 'default', className, children }: PropsWithChildren<ModalProps>) {
    const sizeClass = {
        default: 'max-w-lg',
        lg: 'max-w-3xl',
        xl: 'max-w-5xl',
    }[size];

    useEffect(() => {
        const handler = (e: KeyboardEvent) => {
            if (e.key === 'Escape') onClose();
        };
        if (open) document.addEventListener('keydown', handler);
        return () => document.removeEventListener('keydown', handler);
    }, [open, onClose]);

    if (!open) return null;

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div className="absolute inset-0 bg-black/50" onClick={onClose} />
            <div className={cn('relative z-10 flex max-h-[90vh] w-full flex-col rounded-xl border border-border bg-card shadow-xl', sizeClass, className)}>
                <div className="sticky top-0 z-10 flex items-center justify-between border-b border-border bg-card px-6 py-4">
                    <h2 className="text-base font-semibold">{title}</h2>
                    <button onClick={onClose} className="rounded-md p-1 text-muted-foreground hover:bg-accent">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                <div className="overflow-y-auto px-6 py-4">
                    {children}
                </div>
            </div>
        </div>
    );
}
