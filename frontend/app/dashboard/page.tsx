"use client";

import { useQuery } from "@tanstack/react-query";
import Link from "next/link";
import { useSession } from "next-auth/react";

import { AuthButton } from "@/components/auth-button";

type ApiUser = {
  id: number;
  name: string;
  email: string;
  keycloak_id: string;
};

async function fetchCurrentUser(): Promise<ApiUser> {
  const response = await fetch("/api/me", { cache: "no-store" });

  if (!response.ok) {
    throw new Error("Unauthenticated.");
  }

  return response.json() as Promise<ApiUser>;
}

export default function DashboardPage() {
  const { status } = useSession();
  const { data: user, isLoading, isError } = useQuery({
    queryKey: ["current-user"],
    queryFn: fetchCurrentUser,
    enabled: status === "authenticated",
    retry: false,
  });

  return (
    <div className="flex min-h-screen flex-col bg-zinc-50 font-sans dark:bg-black">
      <header className="flex items-center justify-between border-b border-zinc-200 px-6 py-4 dark:border-zinc-800">
        <div className="flex items-center gap-4">
          <Link
            href="/"
            className="inline-flex h-8 items-center justify-center rounded-lg px-2.5 text-sm font-medium hover:bg-muted"
          >
            Zurück
          </Link>
          <h1 className="text-lg font-semibold text-zinc-900 dark:text-zinc-50">
            Dashboard
          </h1>
        </div>
        <AuthButton />
      </header>

      <main className="mx-auto flex w-full max-w-3xl flex-1 flex-col gap-6 px-6 py-12">
        <section className="rounded-xl border border-zinc-200 bg-white p-6 shadow-sm dark:border-zinc-800 dark:bg-zinc-950">
          <h2 className="mb-4 text-xl font-semibold text-zinc-900 dark:text-zinc-50">
            API-Benutzer
          </h2>

          {isLoading && (
            <p className="text-zinc-600 dark:text-zinc-400">Lade Benutzerdaten...</p>
          )}

          {isError && (
            <p className="text-destructive">
              Fehler beim Laden der Benutzerdaten.
            </p>
          )}

          {user && (
            <dl className="grid gap-3 text-sm">
              <div className="flex justify-between gap-4 border-b border-zinc-100 pb-2 dark:border-zinc-900">
                <dt className="text-zinc-500">ID</dt>
                <dd className="font-medium text-zinc-900 dark:text-zinc-50">
                  {user.id}
                </dd>
              </div>
              <div className="flex justify-between gap-4 border-b border-zinc-100 pb-2 dark:border-zinc-900">
                <dt className="text-zinc-500">Name</dt>
                <dd className="font-medium text-zinc-900 dark:text-zinc-50">
                  {user.name}
                </dd>
              </div>
              <div className="flex justify-between gap-4 border-b border-zinc-100 pb-2 dark:border-zinc-900">
                <dt className="text-zinc-500">E-Mail</dt>
                <dd className="font-medium text-zinc-900 dark:text-zinc-50">
                  {user.email}
                </dd>
              </div>
              <div className="flex justify-between gap-4">
                <dt className="text-zinc-500">Keycloak ID</dt>
                <dd className="font-mono text-xs text-zinc-900 dark:text-zinc-50">
                  {user.keycloak_id}
                </dd>
              </div>
            </dl>
          )}
        </section>
      </main>
    </div>
  );
}
