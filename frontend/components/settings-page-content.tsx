"use client";

import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { useSession } from "next-auth/react";

import { AuthButton } from "@/components/auth-button";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import { Spinner } from "@/components/ui/spinner";
import { useApiEnabled } from "@/hooks/use-api-enabled";
import { getGetCryptoPricesQueryKey } from "@/lib/api/generated/crypto/crypto";
import {
  getGetAuthenticatedUserQueryKey,
  useGetAuthenticatedUser,
  useUpdateAuthenticatedUser,
} from "@/lib/api/generated/user/user";
import { getCurrencyLabel, SUPPORTED_CURRENCIES } from "@/lib/currencies";

export function SettingsPageContent() {
  const queryClient = useQueryClient();
  const { status: sessionStatus } = useSession();
  const apiEnabled = useApiEnabled();
  const [currency, setCurrency] = useState("usd");
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);

  const { data: userResponse, isLoading: isLoadingUser } =
    useGetAuthenticatedUser({
      query: {
        enabled: apiEnabled,
      },
    });

  const user = userResponse?.status === 200 ? userResponse.data : undefined;

  useEffect(() => {
    if (user?.currency) {
      setCurrency(user.currency);
    }
  }, [user?.currency]);

  const updateUser = useUpdateAuthenticatedUser({
    mutation: {
      onSuccess: async (response) => {
        if (response.status !== 200) {
          setError("Währung konnte nicht gespeichert werden.");
          setSuccess(false);
          return;
        }

        setError(null);
        setSuccess(true);
        await queryClient.invalidateQueries({
          queryKey: getGetAuthenticatedUserQueryKey(),
        });
        await queryClient.invalidateQueries({
          queryKey: getGetCryptoPricesQueryKey(),
        });
      },
      onError: () => {
        setError("Währung konnte nicht gespeichert werden.");
        setSuccess(false);
      },
    },
  });

  const isLoading = sessionStatus === "loading" || (apiEnabled && isLoadingUser);
  const hasChanges = user !== undefined && currency !== user.currency;

  const handleSave = () => {
    setError(null);
    setSuccess(false);
    updateUser.mutate({ data: { currency } });
  };

  return (
    <div className="min-h-full bg-zinc-50 dark:bg-black">
      <header className="border-b border-border bg-background">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-4">
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              Securities Manager
            </h1>
            <p className="text-sm text-muted-foreground">Einstellungen</p>
          </div>
          <AuthButton />
        </div>
      </header>

      <main className="mx-auto w-full max-w-5xl px-6 py-8">
        {isLoading ? (
          <div className="flex items-center justify-center py-16">
            <Spinner className="size-6" />
          </div>
        ) : (
          <Card>
            <CardHeader>
              <CardTitle>Anzeigewährung</CardTitle>
              <CardDescription>
                Wähle die Fiat-Währung für Krypto-Preise und Portfoliowerte.
              </CardDescription>
            </CardHeader>
            <CardContent>
              <FieldGroup>
                <Field>
                  <FieldLabel htmlFor="currency">Währung</FieldLabel>
                  <Select
                    value={currency}
                    onValueChange={(value) => {
                      if (value) {
                        setCurrency(value);
                      }
                    }}
                  >
                    <SelectTrigger id="currency" className="w-full max-w-sm">
                      <SelectValue placeholder="Währung wählen">
                        {getCurrencyLabel(currency)}
                      </SelectValue>
                    </SelectTrigger>
                    <SelectContent>
                      {SUPPORTED_CURRENCIES.map((entry) => (
                        <SelectItem key={entry.code} value={entry.code}>
                          {entry.label}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                </Field>

                {error ? (
                  <p className="text-sm text-destructive">{error}</p>
                ) : null}

                {success ? (
                  <p className="text-sm text-green-600 dark:text-green-400">
                    Währung gespeichert.
                  </p>
                ) : null}

                <div>
                  <Button
                    onClick={handleSave}
                    disabled={!hasChanges || updateUser.isPending}
                  >
                    {updateUser.isPending ? (
                      <>
                        <Spinner className="size-4" />
                        Speichern...
                      </>
                    ) : (
                      "Speichern"
                    )}
                  </Button>
                </div>
              </FieldGroup>
            </CardContent>
          </Card>
        )}
      </main>
    </div>
  );
}
