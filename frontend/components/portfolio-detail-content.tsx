"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { format, parseISO } from "date-fns";
import { de } from "date-fns/locale";
import { ArrowLeftIcon, PencilIcon, RefreshCwIcon } from "lucide-react";

import { syncPortfolioAction } from "@/lib/actions/portfolio";
import { PortfolioEditDialog } from "@/components/portfolio-edit-dialog";
import { AuthButton } from "@/components/auth-button";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from "@/components/ui/empty";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import type {
  CryptoPrices,
  GetAuthenticatedUser200,
  Portfolio,
  PortfolioMovement,
  PortfolioSyncStatus,
} from "@/lib/api/generated/models";
import { createPriceFormatter } from "@/lib/format-price";
import {
  calculatePortfolioPerformance,
  formatProfitPercent,
  getProfitToneClass,
} from "@/lib/portfolio-performance";

const amountFormatter = new Intl.NumberFormat("de-DE", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 8,
});

const syncStatusLabels: Record<PortfolioSyncStatus, string> = {
  pending: "Ausstehend",
  syncing: "Synchronisiert…",
  synced: "Synchronisiert",
  failed: "Fehlgeschlagen",
};

const syncStatusVariants: Record<
  PortfolioSyncStatus,
  "default" | "secondary" | "destructive" | "outline"
> = {
  pending: "secondary",
  syncing: "outline",
  synced: "default",
  failed: "destructive",
};

function MovementRow({
  movement,
  cryptoSymbol,
}: {
  movement: PortfolioMovement;
  cryptoSymbol: string;
}) {
  const isDeposit = movement.type === "deposit";
  const signedAmount = isDeposit
    ? `+${amountFormatter.format(Number(movement.amount))}`
    : `−${amountFormatter.format(Number(movement.amount))}`;

  return (
    <TableRow>
      <TableCell>
        {format(parseISO(movement.occurred_at), "dd.MM.yyyy HH:mm", {
          locale: de,
        })}
      </TableCell>
      <TableCell>
        <Badge variant={isDeposit ? "default" : "destructive"}>
          {isDeposit ? "Einzahlung" : "Auszahlung"}
        </Badge>
      </TableCell>
      <TableCell
        className={`text-right font-medium ${
          isDeposit
            ? "text-emerald-600 dark:text-emerald-400"
            : "text-red-600 dark:text-red-400"
        }`}
      >
        {signedAmount} {cryptoSymbol.toUpperCase()}
      </TableCell>
      <TableCell className="font-mono text-xs text-muted-foreground">
        {movement.tx_hash
          ? `${movement.tx_hash.slice(0, 8)}…${movement.tx_hash.slice(-6)}`
          : "—"}
      </TableCell>
    </TableRow>
  );
}

type PortfolioDetailContentProps = {
  portfolioId: number;
  portfolio: Portfolio;
  user: GetAuthenticatedUser200;
  movements: PortfolioMovement[];
  prices: CryptoPrices;
};

export function PortfolioDetailContent({
  portfolioId,
  portfolio,
  user,
  movements,
  prices,
}: PortfolioDetailContentProps) {
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [isSyncing, startSyncTransition] = useTransition();

  const priceUser = prices.prices[portfolio.crypto_id];
  const priceUsd = prices.reference_prices[portfolio.crypto_id];

  const formatPrice = useMemo(
    () => createPriceFormatter(user.currency).format,
    [user.currency],
  );

  const performance = useMemo(() => {
    return calculatePortfolioPerformance({
      balance: Number(portfolio.balance),
      costBasisUsd:
        portfolio.cost_basis_usd !== null
          ? Number(portfolio.cost_basis_usd)
          : null,
      priceUser,
      priceUsd,
    });
  }, [portfolio, priceUser, priceUsd]);

  const portfolioValue = performance?.portfolioValue;

  const handleSync = () => {
    startSyncTransition(async () => {
      await syncPortfolioAction(portfolioId);
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
            <p className="text-sm text-muted-foreground">Portfolio-Details</p>
          </div>
          <AuthButton />
        </div>
      </header>

      <main className="mx-auto w-full max-w-5xl px-6 py-8">
        <div className="mb-6">
          <Link
            href="/portfolios"
            className="inline-flex h-7 items-center gap-1 rounded-[min(var(--radius-md),12px)] px-2.5 text-sm text-muted-foreground hover:bg-muted hover:text-foreground"
          >
            <ArrowLeftIcon className="size-4" />
            Zurück zur Übersicht
          </Link>
        </div>

        {portfolio.sync_status === "failed" && portfolio.sync_error ? (
          <div className="mb-6 rounded-lg border border-destructive/50 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            {portfolio.sync_error}
            {!portfolio.wallet_source ? (
              <span>
                {" "}
                Bitte ergänze die Wallet-Quelle in den Portfolio-Einstellungen.
              </span>
            ) : null}
          </div>
        ) : null}

        <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
          <div className="space-y-2">
            <h2 className="text-2xl font-semibold tracking-tight">
              {portfolio.name}
            </h2>
            <p className="text-sm text-muted-foreground">
              <Badge variant="secondary" className="uppercase">
                {portfolio.crypto_symbol}
              </Badge>{" "}
              {portfolio.crypto_name}
            </p>
            <div className="flex flex-wrap items-center gap-2">
              <Badge variant={syncStatusVariants[portfolio.sync_status]}>
                {syncStatusLabels[portfolio.sync_status]}
              </Badge>
              {portfolio.wallet_source ? (
                <span className="text-xs text-muted-foreground">
                  {portfolio.wallet_source_type === "xpub" ? "xpub" : "Adresse"}:{" "}
                  {portfolio.wallet_source}
                </span>
              ) : null}
            </div>
          </div>
          <div className="flex gap-2">
            <Button
              variant="outline"
              onClick={() => setEditDialogOpen(true)}
            >
              <PencilIcon className="size-4" />
              Bearbeiten
            </Button>
            <Button onClick={handleSync} disabled={isSyncing}>
              <RefreshCwIcon
                className={`size-4 ${isSyncing ? "animate-spin" : ""}`}
              />
              {isSyncing ? "Synchronisiere…" : "Jetzt synchronisieren"}
            </Button>
          </div>
        </div>

        <div className="mb-8 grid gap-4 md:grid-cols-2 lg:grid-cols-4">
          <Card>
            <CardHeader>
              <CardDescription>Bestand</CardDescription>
              <CardTitle className="text-2xl">
                {amountFormatter.format(Number(portfolio.balance))}{" "}
                <span className="text-base font-medium uppercase text-muted-foreground">
                  {portfolio.crypto_symbol}
                </span>
              </CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader>
              <CardDescription>Aktueller Preis</CardDescription>
              <CardTitle className="text-2xl">
                {priceUser !== undefined ? formatPrice(priceUser) : "—"}
              </CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader>
              <CardDescription>Portfoliowert</CardDescription>
              <CardTitle className="text-2xl">
                {portfolioValue !== undefined ? formatPrice(portfolioValue) : "—"}
              </CardTitle>
            </CardHeader>
          </Card>
          <Card>
            <CardHeader>
              <CardDescription>Erfolg</CardDescription>
              <CardTitle className="text-2xl">
                {performance !== null ? (
                  <div className="space-y-1">
                    <p className={getProfitToneClass(performance.profitAmount)}>
                      {formatProfitPercent(performance.profitPercent)}
                    </p>
                    <p
                      className={`text-base font-medium ${getProfitToneClass(performance.profitAmount)}`}
                    >
                      {formatPrice(performance.profitAmount)}
                    </p>
                  </div>
                ) : (
                  "—"
                )}
              </CardTitle>
            </CardHeader>
          </Card>
        </div>

        <div className="space-y-4">
          <h3 className="text-lg font-medium">Transaktionen</h3>

          {movements.length === 0 ? (
            <Empty className="border">
              <EmptyHeader>
                <EmptyTitle>Noch keine Transaktionen</EmptyTitle>
                <EmptyDescription>
                  Transaktionen werden automatisch von der Blockchain
                  importiert, sobald die Synchronisation abgeschlossen ist.
                </EmptyDescription>
              </EmptyHeader>
              <Button onClick={handleSync} disabled={isSyncing}>
                Jetzt synchronisieren
              </Button>
            </Empty>
          ) : (
            <Card>
              <CardContent className="pt-6">
                <Table>
                  <TableHeader>
                    <TableRow>
                      <TableHead>Zeitpunkt</TableHead>
                      <TableHead>Typ</TableHead>
                      <TableHead className="text-right">Menge</TableHead>
                      <TableHead>Tx-Hash</TableHead>
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {movements.map((movement) => (
                      <MovementRow
                        key={movement.id}
                        movement={movement}
                        cryptoSymbol={portfolio.crypto_symbol}
                      />
                    ))}
                  </TableBody>
                </Table>
              </CardContent>
            </Card>
          )}
        </div>
      </main>

      <PortfolioEditDialog
        portfolio={portfolio}
        open={editDialogOpen}
        onOpenChange={setEditDialogOpen}
      />
    </div>
  );
}
