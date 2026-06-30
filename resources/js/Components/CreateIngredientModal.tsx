import { useState } from 'react';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { CurrencyInput } from '@/Components/ui/currency-input';

interface CreateIngredientModalProps {
    open: boolean;
    onClose: () => void;
    onSuccess: (ingredient: { id: number; name: string; base_unit: string }) => void;
    defaultItemType?: string;
}

export default function CreateIngredientModal({
    open,
    onClose,
    onSuccess,
    defaultItemType = 'raw_material',
}: CreateIngredientModalProps) {
    const [form, setForm] = useState({
        name: '',
        item_type: defaultItemType,
        unit_type: 'gramasi',
        base_unit: '',
        unit_price: '',
        minimum_stock: '0',
    });
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState('');

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault();
        setCreating(true);
        setError('');
        try {
            const res = await fetch('/inventory/json', {
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
                    unit_price: Number(form.unit_price) || 0,
                    minimum_stock: Number(form.minimum_stock) || 0,
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
                item_type: defaultItemType,
                unit_type: 'gramasi',
                base_unit: '',
                unit_price: '',
                minimum_stock: '0',
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
            item_type: defaultItemType,
            unit_type: 'gramasi',
            base_unit: '',
            unit_price: '',
            minimum_stock: '0',
        });
        setError('');
        onClose();
    };

    return (
        <Modal open={open} onClose={handleClose} title=Bahan Baru>
            <form onSubmit={handleSubmit} className=space-y-4>
                <div>
                    <Label htmlFor=ing-name>Nama Bahan</Label>
                    <Input
                        id=ing-name
                        value={form.name}
                        onChange={(e) => setForm({ ...form, name: e.target.value })}
                        required
                    />
                </div>
                <div className=grid grid-cols-2 gap-3>
                    <div>
                        <Label htmlFor=ing-unit>Satuan</Label>
                        <Input
                            id=ing-unit
                            value={form.base_unit}
                            onChange={(e) => setForm({ ...form, base_unit: e.target.value })}
                            placeholder=kg, liter, pcs
                            required
                        />
                    </div>
                    <div>
                        <Label htmlFor=ing-price>Harga / satuan (Rp)</Label>
                        <CurrencyInput
                            id=ing-price
                            value={form.unit_price}
                            onChange={(v) => setForm({ ...form, unit_price: v })}
                            required
                        />
                    </div>
                </div>
                <div className=grid grid-cols-2 gap-3>
                    <div>
                        <Label htmlFor=ing-type>Tipe</Label>
                        <select
                            id=ing-type
                            className=flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm
                            value={form.unit_type}
                            onChange={(e) => setForm({ ...form, unit_type: e.target.value })}
                        >
                            <option value=gramasi>Gramasi (timbang)</option>
                            <option value=packaged>Packaged (pcs/kg)</option>
                        </select>
                    </div>
                    <div>
                        <Label htmlFor=ing-min-stock>Stok Minimum</Label>
                        <Input
                            id=ing-min-stock
                            type=number
                            step=0.0001
                            value={form.minimum_stock}
                            onChange={(e) => setForm({ ...form, minimum_stock: e.target.value })}
                        />
                    </div>
                </div>
                {error && <p className=text-sm text-destructive>{error}</p>}
                <div className=flex justify-end gap-2>
                    <Button type=button variant=outline onClick={handleClose}>
                        Batal
                    </Button>
                    <Button type=submit disabled={creating}>
                        {creating ? 'Menyimpan...' : 'Simpan'}
                    </Button>
                </div>
            </form>
        </Modal>
    );
}
