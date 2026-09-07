"use client";

import { useEffect } from "react";
import { signOut, useSession } from "next-auth/react";

import { hasSessionAuthError } from "@/lib/auth/session-error";

export function AuthSessionGuard() {
  const { data: session } = useSession();

  useEffect(() => {
    if (hasSessionAuthError(session)) {
      void signOut({ callbackUrl: "/api/auth/signin" });
    }
  }, [session]);

  return null;
}
