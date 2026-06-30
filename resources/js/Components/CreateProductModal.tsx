import { useState } from 'react';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { CurrencyInput } from '@/Components/ui/currency-input';

interface CreateProductModalProps {
    open: boolean;
    onClose: () => void;
    onSuccess: (product: { id: number; name: string; selling_price: number }) => void;
}

export default function CreateProductModal({
    open,
    onClose,
    onSuccess,
}: CreateProductModalProps) {
    const [form, setForm] = useState({
        name: '',
        unit: 'pcs',
        selling_price: '',
        recipe_type: 'unit' as 'unit' | 'batch',
        is_prep: false,
    });
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setCreating(true);
        setError('');
        try {
            const res = await fetch('/products/json', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-XSRF-TOKEN': decodeURIComponent(
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || ''
                    ),
                },
                body: JSON.stringify({
                    ...form,
                    selling_price: Number(form.selling_price) || 0,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                setError(data.message || 'Gagal menyimpan');
                return;
            }
            onSuccess(data);
            setForm({
                name: '',
                unit: 'pcs',
                selling_price: '',
                recipe_type: 'unit',
                is_prep: false,
            });
        } catch {
            setError('Terjadi kesalahan');
        } finally {
            setCreating(false);
        }
    };

    const handleClose = () => {
        setForm({
            name: '',
            unit: 'pcs',
            selling_price: '',
            recipe_type: 'unit',
            is_prep: false,
        });
        setError('');
        onClose();
    };

    return (
        <Modal open={open} onClose={handleClose} title="Produk Baru">
            <form onSubmit={handleSubmit} className="space-y-4">
                <div>
                    <Label htmlFor="prod-name">Nama Produk</Label>
                    <Input
                        id="prod-name"
                        value={form.name}
                        onChange={(e) => setForm({ ...form, name: e.target.value })}
                        required
                    />
                </div>
                <div className="grid grid-cols-2 gap-3">
                    <div>
                        <Label htmlFor="prod-unit">Satuan</Label>
                        <Input
                            id="prod-unit"
                            value={form.unit}
                            onChange={(e) => setForm({ ...form, unit: e.target.value })}
                            placeholder="pcs, porsi, gelas"
                            required
                        />
                    </div>
                    <div>
                        <Label htmlFor="prod-price">Harga Jual (Rp)</Label>
                        <CurrencyInput
                            id="prod-price"
                            value={form.selling_price}
                            onChange={(v) => setForm({ ...form, selling_price: v })}
                            required
                        />
                    </div>
                </div>
                <div>
                    <Label htmlFor="prod-recipe-type">Tipe Resep</Label>
                    <select
                        id="prod-recipe-type"
                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                        value={form.recipe_type}
                        onChange={(e) => setForm({ ...form, recipe_type: e.target.value as 'unit' | 'batch' })}
                    >
                        <option value="unit">Unit (per porsi)</option>
                        <option value="batch">Batch (per produksi)</option>
                    </select>
                </div>
                {error && <p className="text-sm text-destructive">{error}</p>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={handleClose}>
                        Batal
                    </Button>
                    <Button type="submit" disabled={creating}>
                        {creating ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
