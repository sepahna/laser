# Laser Booking Platform

A production-oriented appointment booking system for a laser beauty clinic.

## Stack
- Next.js App Router + TypeScript
- Supabase PostgreSQL, Auth and Storage
- Server-side payment integrations (Zarinpal and manual bank-transfer proof review)

## Booking rules
- Default appointment duration: 60 minutes; configurable by administrators.
- A booking becomes confirmed only after a verified Zarinpal callback or administrator approval of a bank-transfer receipt.
- Confirmed appointments must be protected against double booking by database-level constraints and transactional slot reservation.
- Pending payment reservations expire automatically after a configurable hold period.

## Environment variables
Copy `.env.example` to `.env.local` and fill values locally. Never commit secrets.

## Status
Initial repository scaffold. Production launch requires configuring Supabase, a server-side deployment target, and merchant payment credentials.
