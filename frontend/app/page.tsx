import { ApiHealthStatus } from "@/components/api-health-status";

export default function Home() {
  return (
    <div className="flex flex-col flex-1 items-center justify-center bg-zinc-50 font-sans dark:bg-black">
      <main className="flex flex-1 w-full max-w-3xl flex-col items-center justify-center gap-8 py-32 px-16">
        <div className="w-full max-w-md space-y-4 text-center">
          <h1 className="text-3xl font-semibold tracking-tight text-black dark:text-zinc-50">
            Securities Manager
          </h1>
          <p className="text-zinc-600 dark:text-zinc-400">
            Type-safe API-Client via Orval + React Query
          </p>
          <ApiHealthStatus />
        </div>
      </main>
    </div>
  );
}
