# Architecture Decision Records (ADR)

Catatan keputusan arsitektur yang signifikan / sulit di-reverse. Format ringan
(lihat [`0000-template.md`](0000-template.md)).

Kapan menulis ADR — lihat [`AGENTS.md` §8](../../AGENTS.md#8-engineering-workflow).
Singkatnya: tulis ADR saat memilih di antara alternatif dengan trade-off nyata
atau saat keputusan mahal untuk dibalik. Jangan untuk hal sepele.

Cara menambah: salin `0000-template.md` → `NNNN-judul-singkat.md` (nomor urut
berikutnya), isi, set status `Accepted`. Keputusan yang menggantikan ADR lama
tandai yang lama `Superseded by ADR-XXXX`.

## Index

| ADR | Judul | Status |
| --- | --- | --- |
| [0001](0001-tech-stack-laravel-inertia-react.md) | Stack: Laravel + Inertia + React | Accepted |
| [0002](0002-money-as-decimal.md) | Uang sebagai decimal, hitung di backend | Accepted |
| [0003](0003-service-layer-and-stock-ledger.md) | Service layer + stok sebagai ledger | Accepted |
| [0004](0004-bot-integration-via-sanctum-api.md) | Integrasi bot lewat API Sanctum | Accepted |
| [0005](0005-async-queue-for-side-effects.md) | Queue (database) untuk efek samping | Accepted |
