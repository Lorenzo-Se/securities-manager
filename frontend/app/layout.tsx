import type { Metadata } from "next";
import { Geist, Geist_Mono, Inter } from "next/font/google";
import "./globals.css";
import { auth } from "@/auth";
import { cn } from "@/lib/utils";
import { Providers } from "@/components/providers";

const inter = Inter({subsets:['latin'],variable:'--font-sans'});

const geistSans = Geist({
  variable: "--font-geist-sans",
  subsets: ["latin"],
});

const geistMono = Geist_Mono({
  variable: "--font-geist-mono",
  subsets: ["latin"],
});

export const metadata: Metadata = {
  title: "Securities Manager",
  description: "Krypto-Portfolios verwalten",
};

export default async function RootLayout({ children }: LayoutProps<"/">) {
  const session = await auth();
  const clientSession = session
    ? { ...session, accessToken: undefined, idToken: undefined }
    : null;

  return (
    <html
      lang="en"
      className={cn("h-full", "antialiased", geistSans.variable, geistMono.variable, "font-sans", inter.variable)}
    >
      <body className="min-h-full flex flex-col">
        <Providers session={clientSession}>{children}</Providers>
      </body>
    </html>
  );
}
