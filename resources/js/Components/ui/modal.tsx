import { PropsWithChildren, useEffect } from 'react';
import { X } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ModalProps {
    open: boolean;
    onClose: () => void;
    title?: string;
    className?: string;
}

export default function Modal({ open, onClose, title, className, children }: PropsWithChildren<ModalProps>) {
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
            <div className={cn('relative z-10 w-full max-w-lg rounded-xl border border-border bg-card p-6 shadow-xl', className)}>
                <div className="mb-4 flex items-center justify-between">
                    <h2 className="text-base font-semibold">{title}</h2>
                    <button onClick={onClose} className="rounded-md p-1 text-muted-foreground hover:bg-accent">
                        <X className="h-4 w-4" />
                    </button>
                </div>
                {children}
            </div>
        </div>
    );
}
