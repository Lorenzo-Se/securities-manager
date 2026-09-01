"use client";

import { useEffect, useState, useTransition } from "react";

import { updateUserCurrencyAction } from "@/lib/actions/user";
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
import type { GetAuthenticatedUser200 } from "@/lib/api/generated/models";
import { getCurrencyLabel, SUPPORTED_CURRENCIES } from "@/lib/currencies";

type SettingsPageContentProps = {
  user: GetAuthenticatedUser200;
};

export function SettingsPageContent({ user }: SettingsPageContentProps) {
  const [currency, setCurrency] = useState(user.currency);
  const [error, setError] = useState<string | null>(null);
  const [success, setSuccess] = useState(false);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    setCurrency(user.currency);
  }, [user.currency]);

  const hasChanges = currency !== user.currency;

  const handleSave = () => {
    setError(null);
    setSuccess(false);

    startTransition(async () => {
      const result = await updateUserCurrencyAction(currency);

      if (!result.ok) {
        setError(result.error);
        setSuccess(false);
        return;
      }

      setError(null);
      setSuccess(true);
    });
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
                  disabled={!hasChanges || isPending}
                >
                  {isPending ? (
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
      </main>
    </div>
  );
}
