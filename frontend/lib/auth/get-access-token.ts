import { auth } from "@/auth";

export async function getAccessToken(): Promise<string | null> {
  const session = await auth();
  return session?.accessToken ?? null;
}
