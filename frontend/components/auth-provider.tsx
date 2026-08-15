"use client";

import { SessionProvider, useSession } from "next-auth/react";
import { useLayoutEffect } from "react";

import { setAccessToken } from "@/lib/api/access-token";

function AccessTokenSync() {
  const { data: session } = useSession();
  const accessToken = session?.accessToken ?? null;

  setAccessToken(accessToken);

  useLayoutEffect(() => {
    setAccessToken(accessToken);
  }, [accessToken]);

  return null;
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  return (
    <SessionProvider>
      <AccessTokenSync />
      {children}
    </SessionProvider>
  );
}
