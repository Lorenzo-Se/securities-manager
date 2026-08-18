"use client";

import { useSession } from "next-auth/react";

export function useApiEnabled(): boolean {
  const { status, data: session } = useSession();

  return status === "authenticated" && Boolean(session?.accessToken);
}
