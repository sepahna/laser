# Laser — Avid Beauty booking platform

## Decisions
- Appointment duration defaults to 60 minutes and is administrator-configurable.
- Business hours default to 12:00–22:00.
- Deposit default: 30% (administrator configurable).
- Payments: Zarinpal online payment and manual bank transfer with receipt upload/admin review.
- Confirm only after a verified Zarinpal callback or admin approval of the transfer receipt.
- Pending holds expire after a configurable interval.
- PostgreSQL exclusion constraint prevents overlapping active appointments.

## Stack
Next.js App Router + TypeScript + Tailwind CSS; Supabase PostgreSQL/Auth/Storage; Zarinpal server-side integration.

## Setup
1. Create a Supabase project and run `supabase/schema.sql` in SQL Editor.
2. Create a **private** Storage bucket named `payment-receipts`; add policies so customers can upload only to their own pending booking and only staff/admin can inspect receipts.
3. Copy `.env.example` to `.env.local` and fill in Supabase URL and publishable/anon key. Keep service-role and payment secrets server-only.
4. Run `npm install` then `npm run dev`.
5. Deploy on a host supporting Next.js server routes. GitHub Pages is static-only and cannot run payment endpoints.

## Current implementation status
This repository contains the initial Next.js frontend shell, admin panel shell, Supabase schema draft, and environment template. The UI payment step is explicitly not a live transaction. Production still requires implementing booking API/transactions, auth and role enforcement, receipt upload/review, Zarinpal request+verification callback, hold expiry, and end-to-end tests before accepting real customers/payments.
