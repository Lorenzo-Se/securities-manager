"use client";

import type { Session } from "next-auth";
import { SessionProvider } from "next-auth/react";

import { AuthSessionGuard } from "@/components/auth-session-guard";

export function AuthProvider({
  children,
  session,
}: {
  children: React.ReactNode;
  session: Session | null;
}) {
  return (
    <SessionProvider session={session}>
      <AuthSessionGuard />
      {children}
    </SessionProvider>
  );
}
