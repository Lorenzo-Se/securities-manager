import "server-only";

import { signOut } from "@/auth";

export async function signOutOnAuthFailure(): Promise<never> {
  await signOut({ redirectTo: "/api/auth/signin" });
  throw new Error("Session expired");
}
