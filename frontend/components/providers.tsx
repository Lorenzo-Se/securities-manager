"use client";

import type { Session } from "next-auth";

import { AuthProvider } from "@/components/auth-provider";

export function Providers({
  children,
  session,
}: {
  children: React.ReactNode;
  session: Session | null;
}) {
  return <AuthProvider session={session}>{children}</AuthProvider>;
}
