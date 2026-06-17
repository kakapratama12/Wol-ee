# ADR-0001: Stack web dashboard — Laravel + Inertia + React

- Status: Accepted
- Tanggal: 2026-06-16
- Pengambil keputusan: tim Wol-ee

## Konteks

Wol-ee butuh web dashboard untuk UMKM F&B yang menampilkan data finansial
(COGS, P&L, pajak, inventory). UI harus enak dilihat dan responsif, tetapi
tim kecil dan ingin menghindari kompleksitas memelihara dua aplikasi terpisah
(API backend + SPA frontend) dengan auth ganda.

## Keputusan

Pakai **Laravel 13** sebagai backend, **Inertia.js + React + TypeScript** untuk
frontend (satu aplikasi, satu deploy), **Tailwind + shadcn/ui** untuk styling.

## Alternatif yang dipertimbangkan

- **Laravel API + React SPA terpisah** — fleksibel, tapi perlu kelola CORS,
  token auth untuk web, dan dua pipeline build/deploy. Overhead terlalu besar
  untuk tim kecil.
- **Blade + Livewire** — paling sederhana di sisi backend, tapi React memberi
  pengalaman UI yang lebih kaya dan ekosistem komponen (shadcn) yang diinginkan.

## Konsekuensi

- Positif: satu repo, satu auth (session), DX React tanpa membangun API publik
  untuk web. Type-safety via TypeScript.
- Trade-off: terikat pada konvensi Inertia (tidak ada REST untuk web); endpoint
  untuk bot tetap perlu API terpisah (lihat ADR-0004).
