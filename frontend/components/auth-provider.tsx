"use client";

import { SessionProvider, useSession } from "next-auth/react";
import { useLayoutEffect } from "react";

import { setAccessToken } from "@/lib/api/access-token";

function AccessTokenSync({ children }: { children: React.ReactNode }) {
  const { data: session } = useSession();
  const accessToken = session?.accessToken ?? null;

  useLayoutEffect(() => {
    setAccessToken(accessToken);
  }, [accessToken]);

  return children;
}

export function AuthProvider({ children }: { children: React.ReactNode }) {
  return (
    <SessionProvider>
      <AccessTokenSync>{children}</AccessTokenSync>
    </SessionProvider>
  );
}
