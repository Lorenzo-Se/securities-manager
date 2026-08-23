"use client";

import { useEffect, useState } from "react";
import { useQueryClient } from "@tanstack/react-query";

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
import {
  getListPortfoliosQueryKey,
  useCreatePortfolio,
} from "@/lib/api/generated/portfolio/portfolio";
import { useListCryptoCoins } from "@/lib/api/generated/crypto/crypto";
import type { CryptoCoin } from "@/lib/api/generated/models";
import { useApiEnabled } from "@/hooks/use-api-enabled";

type PortfolioCreateDialogProps = {
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function PortfolioCreateDialog({
  open,
  onOpenChange,
}: PortfolioCreateDialogProps) {
  const queryClient = useQueryClient();
  const [name, setName] = useState("");
  const [search, setSearch] = useState("");
  const [debouncedSearch, setDebouncedSearch] = useState("");
  const [selectedCoin, setSelectedCoin] = useState<CryptoCoin | null>(null);
  const [error, setError] = useState<string | null>(null);
  const apiEnabled = useApiEnabled();

  const { data: coinsResponse, isLoading: isLoadingCoins } = useListCryptoCoins(
    debouncedSearch ? { search: debouncedSearch } : undefined,
    {
      query: {
        enabled: apiEnabled && open,
      },
    },
  );

  const createPortfolio = useCreatePortfolio({
    mutation: {
      onSuccess: async () => {
        await queryClient.invalidateQueries({
          queryKey: getListPortfoliosQueryKey(),
        });
        resetForm();
        onOpenChange(false);
      },
      onError: () => {
        setError("Portfolio konnte nicht erstellt werden.");
      },
    },
  });

  useEffect(() => {
    const timeout = window.setTimeout(() => {
      setDebouncedSearch(search.trim());
    }, 300);

    return () => window.clearTimeout(timeout);
  }, [search]);

  const coins =
    coinsResponse?.status === 200 ? coinsResponse.data : [];

  const resetForm = () => {
    setName("");
    setSearch("");
    setDebouncedSearch("");
    setSelectedCoin(null);
    setError(null);
  };

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (!selectedCoin) {
      setError("Bitte wähle eine Kryptowährung aus.");
      return;
    }

    createPortfolio.mutate({
      data: {
        name: name.trim(),
        crypto_id: selectedCoin.id,
      },
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
            Gib deinem Portfolio einen Namen und wähle eine Kryptowährung aus.
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
                }}
                placeholder="Suche nach Name oder Symbol"
              />
              <div className="mt-2 max-h-48 overflow-y-auto rounded-lg border border-border">
                {isLoadingCoins ? (
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

                    return (
                      <button
                        key={coin.id}
                        type="button"
                        onClick={() => setSelectedCoin(coin)}
                        className={`flex w-full items-center gap-3 px-3 py-2 text-left text-sm hover:bg-muted ${
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
                  Ausgewählt: {selectedCoin.name} ({selectedCoin.symbol.toUpperCase()})
                </p>
              ) : null}
            </Field>

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
            <Button type="submit" disabled={createPortfolio.isPending}>
              {createPortfolio.isPending ? "Erstelle..." : "Erstellen"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
