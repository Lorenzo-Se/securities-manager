"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { format, parseISO } from "date-fns";
import { de } from "date-fns/locale";
import { ArrowLeftIcon, PlusIcon, Trash2Icon } from "lucide-react";

import { deletePortfolioMovementAction } from "@/lib/actions/movement";
import { PortfolioMovementDialog } from "@/components/portfolio-movement-dialog";
import { AuthButton } from "@/components/auth-button";
import {
  AlertDialog,
  AlertDialogAction,
  AlertDialogCancel,
  AlertDialogContent,
  AlertDialogDescription,
  AlertDialogFooter,
  AlertDialogHeader,
  AlertDialogTitle,
  AlertDialogTrigger,
} from "@/components/ui/alert-dialog";
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

function MovementRow({
  movement,
  portfolioId,
  cryptoSymbol,
}: {
  movement: PortfolioMovement;
  portfolioId: number;
  cryptoSymbol: string;
}) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [isPending, startTransition] = useTransition();

  const isDeposit = movement.type === "deposit";
  const signedAmount = isDeposit
    ? `+${amountFormatter.format(Number(movement.amount))}`
    : `−${amountFormatter.format(Number(movement.amount))}`;

  const handleDelete = () => {
    setIsDeleting(true);
    startTransition(async () => {
      const result = await deletePortfolioMovementAction(
        portfolioId,
        movement.id,
      );
      if (!result.ok) {
        setIsDeleting(false);
      }
    });
  };

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
          isDeposit ? "text-emerald-600 dark:text-emerald-400" : "text-red-600 dark:text-red-400"
        }`}
      >
        {signedAmount} {cryptoSymbol.toUpperCase()}
      </TableCell>
      <TableCell className="text-right">
        <AlertDialog>
          <AlertDialogTrigger
            render={
              <Button
                variant="outline"
                size="icon-sm"
                disabled={isDeleting || isPending}
              />
            }
          >
            <Trash2Icon className="size-4" />
            <span className="sr-only">Buchung löschen</span>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Buchung löschen?</AlertDialogTitle>
              <AlertDialogDescription>
                Diese Buchung wird dauerhaft entfernt.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Abbrechen</AlertDialogCancel>
              <AlertDialogAction variant="destructive" onClick={handleDelete}>
                Löschen
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
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
  const [movementDialogOpen, setMovementDialogOpen] = useState(false);

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

        <div className="mb-6 flex flex-wrap items-start justify-between gap-4">
          <div className="space-y-1">
            <h2 className="text-2xl font-semibold tracking-tight">
              {portfolio.name}
            </h2>
            <p className="text-sm text-muted-foreground">
              <Badge variant="secondary" className="uppercase">
                {portfolio.crypto_symbol}
              </Badge>{" "}
              {portfolio.crypto_name}
            </p>
          </div>
          <Button onClick={() => setMovementDialogOpen(true)}>
            <PlusIcon className="size-4" />
            Buchung hinzufügen
          </Button>
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
          <h3 className="text-lg font-medium">Buchungen</h3>

          {movements.length === 0 ? (
            <Empty className="border">
              <EmptyHeader>
                <EmptyTitle>Noch keine Buchungen</EmptyTitle>
                <EmptyDescription>
                  Füge deine erste Ein- oder Auszahlung hinzu.
                </EmptyDescription>
              </EmptyHeader>
              <Button onClick={() => setMovementDialogOpen(true)}>
                Buchung hinzufügen
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
                      <TableHead className="w-12" />
                    </TableRow>
                  </TableHeader>
                  <TableBody>
                    {movements.map((movement) => (
                      <MovementRow
                        key={movement.id}
                        movement={movement}
                        portfolioId={portfolioId}
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

      <PortfolioMovementDialog
        portfolioId={portfolioId}
        open={movementDialogOpen}
        onOpenChange={setMovementDialogOpen}
      />
    </div>
  );
}
