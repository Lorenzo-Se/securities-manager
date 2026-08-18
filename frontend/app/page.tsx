import { auth } from "@/auth";
import { redirect } from "next/navigation";

import { AuthButton } from "@/components/auth-button";

export default async function Home() {
  const session = await auth();

  if (session) {
    redirect("/portfolios");
  }

  return (
    <div className="flex flex-1 flex-col items-center justify-center bg-zinc-50 font-sans dark:bg-black">
      <main className="flex w-full max-w-3xl flex-1 flex-col items-center justify-center gap-8 px-16 py-32">
        <div className="absolute top-6 right-6">
          <AuthButton />
        </div>
        <div className="w-full max-w-md space-y-4 text-center">
          <h1 className="text-3xl font-semibold tracking-tight text-black dark:text-zinc-50">
            Securities Manager
          </h1>
          <p className="text-zinc-600 dark:text-zinc-400">
            Verwalte deine Krypto-Portfolios an einem Ort.
          </p>
        </div>
      </main>
    </div>
  );
}
