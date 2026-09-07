import { auth } from "@/auth";
import { hasSessionAuthError } from "@/lib/auth/session-error";
import { signOutOnAuthFailure } from "@/lib/auth/sign-out-on-auth-failure";

export async function getAccessToken(): Promise<string | null> {
  const session = await auth();

  if (!session) {
    return null;
  }

  if (hasSessionAuthError(session) || !session.accessToken) {
    await signOutOnAuthFailure();
  }

  return session.accessToken ?? null;
}
