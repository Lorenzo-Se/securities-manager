"use client";

import { useEffect, useState, useTransition } from "react";

import { updatePortfolioAction } from "@/lib/actions/portfolio";
import { Button } from "@/components/ui/button";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import type { Portfolio } from "@/lib/api/generated/models";

type PortfolioEditDialogProps = {
  portfolio: Portfolio;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function PortfolioEditDialog({
  portfolio,
  open,
  onOpenChange,
}: PortfolioEditDialogProps) {
  const [name, setName] = useState(portfolio.name);
  const [walletSource, setWalletSource] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    if (open) {
      setName(portfolio.name);
      setWalletSource("");
      setError(null);
    }
  }, [open, portfolio.name]);

  const walletSourceLabel =
    portfolio.wallet_source_type === "xpub"
      ? "Extended Public Key (xpub)"
      : "Wallet-Adresse";

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    const payload: { name: string; wallet_source?: string } = {
      name: name.trim(),
    };

    if (walletSource.trim()) {
      payload.wallet_source = walletSource.trim();
    }

    startTransition(async () => {
      const result = await updatePortfolioAction(portfolio.id, payload);

      if (!result.ok) {
        setError(result.error);
        return;
      }

      onOpenChange(false);
    });
  };

  return (
    <Dialog open={open} onOpenChange={onOpenChange}>
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Portfolio bearbeiten</DialogTitle>
          <DialogDescription>
            Name und Wallet-Quelle anpassen. Eine neue Wallet-Quelle löst einen
            erneuten Sync aus.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit}>
          <FieldGroup className="gap-4">
            <Field>
              <FieldLabel htmlFor="edit-portfolio-name">Name</FieldLabel>
              <Input
                id="edit-portfolio-name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                maxLength={100}
                required
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="edit-wallet-source">
                {walletSourceLabel}
              </FieldLabel>
              <Input
                id="edit-wallet-source"
                value={walletSource}
                onChange={(event) => setWalletSource(event.target.value)}
                placeholder={
                  portfolio.wallet_source
                    ? `Aktuell: ${portfolio.wallet_source}`
                    : "Neue Wallet-Quelle eingeben"
                }
              />
              <p className="text-xs text-muted-foreground">
                Leer lassen, um die bestehende Quelle beizubehalten.
              </p>
            </Field>

            {portfolio.last_synced_at ? (
              <p className="text-sm text-muted-foreground">
                Zuletzt synchronisiert:{" "}
                {new Date(portfolio.last_synced_at).toLocaleString("de-DE")}
              </p>
            ) : null}

            {error ? (
              <p className="text-sm text-destructive">{error}</p>
            ) : null}
          </FieldGroup>

          <DialogFooter className="mt-6">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Abbrechen
            </Button>
            <Button type="submit" disabled={isPending}>
              {isPending ? "Speichere..." : "Speichern"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
