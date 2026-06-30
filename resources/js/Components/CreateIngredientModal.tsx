import { useState } from 'react';
import Modal from '@/Components/ui/modal';
import { Button } from '@/Components/ui/button';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Calculator } from 'lucide-react';

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
        current_stock: '',
        minimum_stock: '0',
    });
    const [creating, setCreating] = useState(false);
    const [error, setError] = useState('');

    // Calculator state
    const [calcOpen, setCalcOpen] = useState(false);
    const [calcQty, setCalcQty] = useState('');
    const [calcHarga, setCalcHarga] = useState('');

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
                        document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1] || '',
                    ),
                },
                body: JSON.stringify({
                    ...form,
                    unit_price: Number(form.unit_price) || 0,
                    current_stock: Number(form.current_stock) || 0,
                    minimum_stock: Number(form.minimum_stock) || 0,
                }),
            });
            const data = await res.json();
            if (!res.ok) {
                setError(data.message || 'Gagal menyimpan');
                return;
            }
            onSuccess(data);
            resetForm();
        } catch {
            setError('Terjadi kesalahan');
        } finally {
            setCreating(false);
        }
    };

    const resetForm = () => {
        setForm({
            name: '',
            item_type: defaultItemType,
            unit_type: 'gramasi',
            base_unit: '',
            unit_price: '',
            current_stock: '',
            minimum_stock: '0',
        });
        setError('');
    };

    const handleClose = () => {
        resetForm();
        onClose();
    };

    const openCalculator = () => {
        setCalcQty('');
        setCalcHarga('');
        setCalcOpen(true);
    };

    const applyCalculator = () => {
        const result = parseFloat(calcHarga) / parseFloat(calcQty);
        setForm({ ...form, unit_price: String(Math.round(result)) });
        setCalcOpen(false);
    };

    return (
        <>
            <Modal open={open} onClose={handleClose} title="Bahan Baru">
                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <Label htmlFor="ing-name">Nama Bahan</Label>
                        <Input
                            id="ing-name"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            required
                        />
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="ing-type">Tipe</Label>
                            <select
                                id="ing-type"
                                className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm"
                                value={form.unit_type}
                                onChange={(e) => setForm({ ...form, unit_type: e.target.value })}
                            >
                                <option value="gramasi">Gramasi (timbang)</option>
                                <option value="packaged">Packaged (pcs/kg)</option>
                            </select>
                        </div>
                        <div>
                            <Label htmlFor="ing-unit">Satuan dasar</Label>
                            <Input
                                id="ing-unit"
                                value={form.base_unit}
                                onChange={(e) => setForm({ ...form, base_unit: e.target.value })}
                                placeholder="g, ml, butir, sachet"
                                required
                            />
                        </div>
                    </div>
                    <div>
                        <Label htmlFor="ing-price">Harga per satuan dasar (Rp)</Label>
                        <div className="flex gap-2">
                            <Input
                                id="ing-price"
                                type="number"
                                step="0.0001"
                                value={form.unit_price}
                                onChange={(e) => setForm({ ...form, unit_price: e.target.value })}
                                className="flex-1"
                                required
                            />
                            <Button
                                type="button"
                                variant="outline"
                                size="icon"
                                title="Kalkulator harga"
                                disabled={!form.base_unit}
                                onClick={openCalculator}
                            >
                                <Calculator className="h-4 w-4" />
                            </Button>
                        </div>
                    </div>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label htmlFor="ing-current-stock">Stok awal</Label>
                            <Input
                                id="ing-current-stock"
                                type="number"
                                step="0.0001"
                                value={form.current_stock}
                                onChange={(e) =>
                                    setForm({ ...form, current_stock: e.target.value })
                                }
                            />
                        </div>
                        <div>
                            <Label htmlFor="ing-min-stock">Stok minimum</Label>
                            <Input
                                id="ing-min-stock"
                                type="number"
                                step="0.0001"
                                value={form.minimum_stock}
                                onChange={(e) =>
                                    setForm({ ...form, minimum_stock: e.target.value })
                                }
                            />
                        </div>
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

            {/* Calculator Modal */}
            <Modal
                open={calcOpen}
                onClose={() => setCalcOpen(false)}
                title="Kalkulator Harga Satuan"
            >
                <div className="space-y-4">
                    <p className="text-sm text-muted-foreground">
                        Masukkan jumlah yang dibeli (dalam{' '}
                        <strong>{form.base_unit || 'satuan'}</strong>) dan harga belinya.
                    </p>
                    <div className="grid grid-cols-2 gap-3">
                        <div>
                            <Label>Jumlah dibeli ({form.base_unit || '...'})</Label>
                            <Input
                                type="number"
                                step="0.0001"
                                value={calcQty}
                                onChange={(e) => setCalcQty(e.target.value)}
                                placeholder="misal: 5000"
                            />
                        </div>
                        <div>
                            <Label>Harga beli (Rp)</Label>
                            <Input
                                type="number"
                                step="1"
                                value={calcHarga}
                                onChange={(e) => setCalcHarga(e.target.value)}
                                placeholder="misal: 50000"
                            />
                        </div>
                    </div>
                    {parseFloat(calcQty) > 0 && parseFloat(calcHarga) > 0 && (
                        <div className="rounded-md bg-muted p-3 text-center">
                            <p className="text-sm text-muted-foreground">
                                Estimasi harga per {form.base_unit || 'satuan'}:
                            </p>
                            <p className="text-lg font-bold">
                                {new Intl.NumberFormat('id-ID').format(
                                    Math.round(parseFloat(calcHarga) / parseFloat(calcQty)),
                                )}{' '}
                                Rp/{form.base_unit || 'satuan'}
                            </p>
                        </div>
                    )}
                    <div className="flex justify-end gap-2">
                        <Button type="button" variant="outline" onClick={() => setCalcOpen(false)}>
                            Batal
                        </Button>
                        <Button
                            type="button"
                            disabled={!calcQty || !calcHarga || parseFloat(calcQty) <= 0}
                            onClick={applyCalculator}
                        >
                            Gunakan
                        </Button>
                    </div>
                </div>
            </Modal>
        </>
    );
}
