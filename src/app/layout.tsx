import type { Metadata } from "next";
import "./globals.css";
export const metadata: Metadata = { title: "آوید بیوتی | رزرو آنلاین لیزر", description: "سامانه رزرو آنلاین خدمات لیزر آوید بیوتی" };
export default function RootLayout({children}:{children:React.ReactNode}) { return <html lang="fa" dir="rtl"><body>{children}</body></html>; }
