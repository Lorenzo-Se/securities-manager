export const REFRESH_ACCESS_TOKEN_ERROR = "RefreshAccessTokenError";

export function hasSessionAuthError(
  session: { error?: string } | null | undefined,
): boolean {
  return session?.error === REFRESH_ACCESS_TOKEN_ERROR;
}
