"use client";

import { useEffect, useState, useTransition } from "react";

import { searchCryptoCoinsAction } from "@/lib/actions/crypto";
import { createPortfolioAction } from "@/lib/actions/portfolio";
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
import { Spinner } from "@/components/ui/spinner";
import type { CryptoCoin } from "@/lib/api/generated/models";

type PortfolioCreateDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function PortfolioCreateDialog({
  open,
  onOpenChange,
}: PortfolioCreateDialogProps) {
  const [name, setName] = useState("");
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [walletSource, setWalletSource] = useState("");
  const [selectedCoin, setSelectedCoin] = useState<CryptoCoin | null>(null);
  const [coins, setCoins] = useState<CryptoCoin[]>([]);
  const [error, setError] = useState<string | null>(null);
  const [isSearching, setIsSearching] = useState(false);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setDebouncedSearch(search.trim());
    }, 300);

    return () => window.clearTimeout(timeout);
  }, [search]);

  useEffect(() => {
    if (!open) {
      return;
    }

    let cancelled = false;

    const loadCoins = async () => {
      setIsSearching(true);
      const result = await searchCryptoCoinsAction(debouncedSearch);

      if (cancelled) {
        return;
      }

      if (result.ok) {
        setCoins(result.data ?? []);
      } else {
        setCoins([]);
      }

      setIsSearching(false);
    };

    void loadCoins();

    return () => {
      cancelled = true;
    };
  }, [debouncedSearch, open]);

  const resetForm = () => {
    setName("");
    setSearch("");
    setDebouncedSearch("");
    setWalletSource("");
    setSelectedCoin(null);
    setCoins([]);
    setError(null);
  };

  const walletSourceLabel =
    selectedCoin?.wallet_source_type === "xpub"
      ? "Extended Public Key (xpub)"
      : "Wallet-Adresse";

  const walletSourcePlaceholder =
    selectedCoin?.wallet_source_type === "xpub"
      ? "xpub6CUGRUon..."
      : "0x... oder Chain-Adresse";

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (!selectedCoin) {
      setError("Bitte wähle eine Kryptowährung aus.");
      return;
    }

    if (!selectedCoin.sync_supported) {
      setError("Diese Kryptowährung wird für On-Chain-Sync nicht unterstützt.");
      return;
    }

    if (!walletSource.trim()) {
      setError(`Bitte gib eine ${walletSourceLabel} an.`);
      return;
    }

    startTransition(async () => {
      const result = await createPortfolioAction({
        name: name.trim(),
        crypto_id: selectedCoin.id,
        wallet_source: walletSource.trim(),
      });

      if (!result.ok) {
        setError(result.error);
        return;
      }

      resetForm();
      onOpenChange(false);
    });
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          resetForm();
        }
        onOpenChange(nextOpen);
      }}
    >
      <DialogContent className="sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Portfolio erstellen</DialogTitle>
          <DialogDescription>
            Wähle eine Kryptowährung und gib die Wallet-Quelle für den
            automatischen Transaktions-Import an.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit}>
          <FieldGroup className="gap-4">
            <Field>
              <FieldLabel htmlFor="portfolio-name">Name</FieldLabel>
              <Input
                id="portfolio-name"
                value={name}
                onChange={(event) => setName(event.target.value)}
                placeholder="z. B. Mein Bitcoin"
                maxLength={100}
                required
              />
            </Field>

            <Field>
              <FieldLabel htmlFor="crypto-search">Kryptowährung</FieldLabel>
              <Input
                id="crypto-search"
                value={search}
                onChange={(event) => {
                  setSearch(event.target.value);
                  setSelectedCoin(null);
                  setWalletSource("");
                }}
                placeholder="Suche nach Name oder Symbol"
              />
              <div className="mt-2 max-h-48 overflow-y-auto rounded-lg border border-border">
                {isSearching ? (
                  <div className="flex items-center justify-center py-6">
                    <Spinner />
                  </div>
                ) : coins.length === 0 ? (
                  <p className="px-3 py-4 text-sm text-muted-foreground">
                    Keine Kryptowährungen gefunden.
                  </p>
                ) : (
                  coins.map((coin) => {
                    const isSelected = selectedCoin?.id === coin.id;
                    const isDisabled = !coin.sync_supported;

                    return (
                      <button
                        key={coin.id}
                        type="button"
                        disabled={isDisabled}
                        onClick={() => {
                          setSelectedCoin(coin);
                          setWalletSource("");
                        }}
                        className={`flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted disabled:cursor-not-allowed disabled:opacity-50 ${
                          isSelected ? "bg-muted font-medium" : ""
                        }`}
                      >
                        {coin.image ? (
                          <img
                            src={coin.image}
                            alt=""
                            width={24}
                            height={24}
                            className="size-6 shrink-0 rounded-full"
                          />
                        ) : (
                          <span className="size-6 shrink-0 rounded-full bg-muted" />
                        )}
                        <span className="flex-1 truncate">{coin.name}</span>
                        <span className="text-muted-foreground uppercase">
                          {coin.symbol}
                        </span>
                        {!coin.sync_supported ? (
                          <span className="text-xs text-muted-foreground">
                            Kein Sync
                          </span>
                        ) : null}
                      </button>
                    );
                  })
                )}
              </div>
              {selectedCoin ? (
                <p className="flex items-center gap-2 text-sm text-muted-foreground">
                  {selectedCoin.image ? (
                    <img
                      src={selectedCoin.image}
                      alt=""
                      width={20}
                      height={20}
                      className="size-5 rounded-full"
                    />
                  ) : null}
                  Ausgewählt: {selectedCoin.name} (
                  {selectedCoin.symbol.toUpperCase()})
                </p>
              ) : null}
            </Field>

            {selectedCoin?.sync_supported ? (
              <Field>
                <FieldLabel htmlFor="wallet-source">
                  {walletSourceLabel}
                </FieldLabel>
                <Input
                  id="wallet-source"
                  value={walletSource}
                  onChange={(event) => setWalletSource(event.target.value)}
                  placeholder={walletSourcePlaceholder}
                  required
                />
              </Field>
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
              {isPending ? "Erstelle..." : "Erstellen"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
