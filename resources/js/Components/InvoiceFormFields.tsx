import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/Components/ui/button';
import { CurrencyInput } from '@/Components/ui/currency-input';
import { Input } from '@/Components/ui/input';
import { Select } from '@/Components/ui/select';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/Components/ui/table';
import { formatRupiah } from '@/lib/format';

export interface FeeRow {
    name: string;
    type: 'fixed' | 'percentage';
    value: string;
}

interface InvoiceItem {
    description: string;
    quantity: string;
    unit_price: string;
}

interface Props {
    items: InvoiceItem[];
    fees: FeeRow[];
    onUpdateItem: (i: number, key: string, value: string) => void;
    onAddItem: () => void;
    onRemoveItem: (i: number) => void;
    onUpdateFee: (i: number, key: keyof FeeRow, value: string) => void;
    onAddFee: () => void;
    onRemoveFee: (i: number) => void;
}

export default function InvoiceFormFields({
    items,
    fees,
    onUpdateItem,
    onAddItem,
    onRemoveItem,
    onUpdateFee,
    onAddFee,
    onRemoveFee,
}: Props) {
    const subtotal = items.reduce((sum, row) => {
        return sum + (parseFloat(row.quantity) || 0) * (parseFloat(row.unit_price) || 0);
    }, 0);

    const totalFees = fees.reduce((sum, fee) => {
        const val = parseFloat(fee.value) || 0;
        if (fee.type === 'percentage') {
            return sum + (subtotal * val) / 100;
        }
        return sum + val;
    }, 0);

    const total = subtotal + totalFees;

    return (
        <div className="space-y-4">
            {/* Items */}
            <div className="space-y-2">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="flex-1">Deskripsi</TableHead>
                            <TableHead className="w-36">Qty</TableHead>
                            <TableHead className="w-72">Harga Satuan</TableHead>
                            <TableHead className="w-10"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {items.map((row, i) => (
                            <TableRow key={i}>
                                <TableCell>
                                    <Input
                                        value={row.description}
                                        onChange={(e) =>
                                            onUpdateItem(i, 'description', e.target.value)
                                        }
                                        placeholder="Deskripsi item"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Input
                                        type="number"
                                        min="1"
                                        value={row.quantity}
                                        onChange={(e) =>
                                            onUpdateItem(i, 'quantity', e.target.value)
                                        }
                                    />
                                </TableCell>
                                <TableCell>
                                    <CurrencyInput
                                        value={row.unit_price}
                                        onChange={(v) => onUpdateItem(i, 'unit_price', v)}
                                    />
                                </TableCell>
                                <TableCell>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onRemoveItem(i)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Button type="button" variant="outline" size="sm" onClick={onAddItem}>
                    <Plus className="mr-1 h-4 w-4" />
                    Tambah Item
                </Button>
            </div>

            {/* Fees */}
            <div className="space-y-2 border-t pt-4">
                <Table>
                    <TableHeader>
                        <TableRow>
                            <TableHead className="flex-1">Nama</TableHead>
                            <TableHead className="w-36">Tipe</TableHead>
                            <TableHead className="w-72">Nilai</TableHead>
                            <TableHead className="w-10"></TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody>
                        {fees.map((fee, i) => (
                            <TableRow key={i}>
                                <TableCell>
                                    <Input
                                        value={fee.name}
                                        onChange={(e) => onUpdateFee(i, 'name', e.target.value)}
                                        placeholder="Delivery Fee, PPN, dll"
                                    />
                                </TableCell>
                                <TableCell>
                                    <Select
                                        value={fee.type}
                                        onChange={(e) =>
                                            onUpdateFee(
                                                i,
                                                'type',
                                                e.target.value as 'fixed' | 'percentage',
                                            )
                                        }
                                    >
                                        <option value="fixed">Nominal (Rp)</option>
                                        <option value="percentage">Persen (%)</option>
                                    </Select>
                                </TableCell>
                                <TableCell>
                                    <Input
                                        type="number"
                                        min="0"
                                        step={fee.type === 'percentage' ? '0.5' : '1000'}
                                        value={fee.value}
                                        onChange={(e) => onUpdateFee(i, 'value', e.target.value)}
                                        placeholder={fee.type === 'percentage' ? '11' : '0'}
                                    />
                                </TableCell>
                                <TableCell>
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onRemoveFee(i)}
                                    >
                                        <Trash2 className="h-4 w-4 text-destructive" />
                                    </Button>
                                </TableCell>
                            </TableRow>
                        ))}
                    </TableBody>
                </Table>
                <Button type="button" variant="outline" size="sm" onClick={onAddFee}>
                    <Plus className="mr-1 h-4 w-4" />
                    Tambah Biaya
                </Button>
            </div>

            {/* Total */}
            {subtotal > 0 && (
                <div className="space-y-2 border-t pt-4">
                    <div className="flex justify-end text-sm">
                        <span className="text-muted-foreground">Subtotal:</span>
                        <span className="ml-4 font-medium">{formatRupiah(subtotal)}</span>
                    </div>
                    {totalFees > 0 && (
                        <div className="flex justify-end text-sm">
                            <span className="text-muted-foreground">Biaya Tambahan:</span>
                            <span className="ml-4 font-medium">{formatRupiah(totalFees)}</span>
                        </div>
                    )}
                    <div className="flex justify-end text-base font-bold">
                        <span>Total:</span>
                        <span className="ml-4">{formatRupiah(total)}</span>
                    </div>
                </div>
            )}
        </div>
    );
}
