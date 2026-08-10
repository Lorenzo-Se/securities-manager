"use client";

import { useGetHealth } from "@/lib/api/generated/system/system";

export function ApiHealthStatus() {
  const { data, error, isPending, isError } = useGetHealth();

  if (isPending) {
    return (
      <p className="text-sm text-zinc-500">API-Status wird geladen…</p>
    );
  }

  if (isError) {
    return (
      <p className="text-sm text-red-600">
        API nicht erreichbar:{" "}
        {error instanceof Error ? error.message : "Unbekannter Fehler"}
      </p>
    );
  }

  return (
    <div className="rounded-lg border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm dark:border-zinc-800 dark:bg-zinc-950">
      <p className="font-medium text-zinc-900 dark:text-zinc-100">
        Backend verbunden
      </p>
      <p className="mt-1 text-zinc-600 dark:text-zinc-400">
        Status: {data.data.status} · {data.data.timestamp}
      </p>
    </div>
  );
}
