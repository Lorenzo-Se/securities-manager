"use client";

import Link from "next/link";
import { signIn, signOut, useSession } from "next-auth/react";

import { Button } from "@/components/ui/button";

export function AuthButton() {
  const { data: session, status } = useSession();

  if (status === "loading") {
    return (
      <Button variant="outline" disabled>
        Laden...
      </Button>
    );
  }

  if (!session) {
    return (
      <Button onClick={() => signIn("keycloak")}>Anmelden</Button>
    );
  }

  return (
    <div className="flex items-center gap-3">
      <span className="text-sm text-zinc-600 dark:text-zinc-400">
        {session.user?.name ?? session.user?.email}
      </span>
      <Link
        href="/portfolios"
        className="inline-flex h-8 items-center justify-center rounded-lg border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted"
      >
        Portfolios
      </Link>
      <Link
        href="/settings"
        className="inline-flex h-8 items-center justify-center rounded-lg border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted"
      >
        Einstellungen
      </Link>
      <Button variant="outline" onClick={() => signOut()}>
        Abmelden
      </Button>
    </div>
  );
}
