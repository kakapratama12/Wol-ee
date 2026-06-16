# ADR-0002: Uang disimpan sebagai decimal, dihitung di backend

- Status: Accepted
- Tanggal: 2026-06-16
- Pengambil keputusan: tim Wol-ee

## Konteks

Seluruh nilai produk adalah finansial: harga bahan, COGS, margin, omset, pajak.
Kesalahan pembulatan akan langsung merusak kepercayaan pengguna (UMKM mengambil
keputusan pajak/harga dari angka ini). PHP/JS `float` tidak akurat untuk uang.

## Keputusan

Simpan semua nilai uang sebagai kolom `decimal` di Postgres dan cast `decimal`
di Eloquent. **Tidak pernah** pakai `float` untuk perhitungan uang. Semua
kalkulasi (COGS, pajak, margin, P&L) dilakukan di backend (service layer);
frontend hanya memformat tampilan (`formatRupiah`).

## Alternatif yang dipertimbangkan

- **Integer rupiah (cents)** — akurat, tapi rupiah jarang pakai sen dan resep
  butuh harga per-gram pecahan; `decimal` lebih ekspresif untuk gramasi.
- **Float** — ditolak: tidak akurat untuk uang.
- **Hitung di frontend** — ditolak: logika bisnis tidak boleh terduplikasi dan
  tidak boleh dipercaya dari client.

## Konsekuensi

- Positif: presisi konsisten; satu sumber kebenaran untuk perhitungan.
- Trafe-off: perlu disiplin casting & format; agregasi pakai presisi decimal.
- Dijaga: review setiap kalkulasi baru agar tidak menyelipkan `float`.
