"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { format, parseISO } from "date-fns";
import { de } from "date-fns/locale";
import { ArrowLeftIcon, PlusIcon, Trash2Icon } from "lucide-react";

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
import { Spinner } from "@/components/ui/spinner";
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from "@/components/ui/table";
import { useGetCryptoPrices } from "@/lib/api/generated/crypto/crypto";
import {
  getGetPortfolioQueryKey,
  getListPortfolioMovementsQueryKey,
  getListPortfoliosQueryKey,
  useDeletePortfolioMovement,
  useGetPortfolio,
  useListPortfolioMovements,
} from "@/lib/api/generated/portfolio/portfolio";
import { useGetAuthenticatedUser } from "@/lib/api/generated/user/user";
import type { PortfolioMovement } from "@/lib/api/generated/models";
import { useApiEnabled } from "@/hooks/use-api-enabled";
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
  const queryClient = useQueryClient();
  const [isDeleting, setIsDeleting] = useState(false);

  const deleteMovement = useDeletePortfolioMovement({
    mutation: {
      onSuccess: async () => {
        await Promise.all([
          queryClient.invalidateQueries({
            queryKey: getListPortfolioMovementsQueryKey(portfolioId),
          }),
          queryClient.invalidateQueries({
            queryKey: getGetPortfolioQueryKey(portfolioId),
          }),
          queryClient.invalidateQueries({
            queryKey: getListPortfoliosQueryKey(),
          }),
        ]);
        setIsDeleting(false);
      },
      onError: () => {
        setIsDeleting(false);
      },
    },
  });

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
                disabled={isDeleting || deleteMovement.isPending}
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
              <AlertDialogAction
                variant="destructive"
                onClick={() => {
                  setIsDeleting(true);
                  deleteMovement.mutate({
                    portfolio: portfolioId,
                    movement: movement.id,
                  });
                }}
              >
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
};

export function PortfolioDetailContent({
  portfolioId,
}: PortfolioDetailContentProps) {
  const [movementDialogOpen, setMovementDialogOpen] = useState(false);
  const apiEnabled = useApiEnabled();

  const {
    data: portfolioResponse,
    isLoading: isLoadingPortfolio,
    isError: isPortfolioError,
  } = useGetPortfolio(portfolioId, {
    query: {
      enabled: apiEnabled,
    },
  });

  const portfolio =
    portfolioResponse?.status === 200 ? portfolioResponse.data : null;

  const { data: userResponse } = useGetAuthenticatedUser({
    query: {
      enabled: apiEnabled,
    },
  });

  const userCurrency =
    userResponse?.status === 200 ? userResponse.data.currency : "usd";

  const {
    data: movementsResponse,
    isLoading: isLoadingMovements,
    isError: isMovementsError,
  } = useListPortfolioMovements(portfolioId, {
    query: {
      enabled: apiEnabled && portfolio !== null,
    },
  });

  const movements =
    movementsResponse?.status === 200 ? movementsResponse.data : [];

  const { data: pricesResponse, isLoading: isPricesLoading } =
    useGetCryptoPrices(
      { ids: portfolio?.crypto_id ?? "" },
      {
        query: {
          enabled: apiEnabled && portfolio !== null,
        },
      },
    );

  const pricesData =
    pricesResponse?.status === 200 ? pricesResponse.data : undefined;

  const priceUser = pricesData?.prices[portfolio?.crypto_id ?? ""];
  const priceUsd = pricesData?.reference_prices[portfolio?.crypto_id ?? ""];

  const formatPrice = useMemo(
    () => createPriceFormatter(userCurrency).format,
    [userCurrency],
  );

  const performance = useMemo(() => {
    if (!portfolio) {
      return null;
    }

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

  const isLoading = isLoadingPortfolio || isLoadingMovements;

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

        {isLoading ? (
          <div className="flex items-center justify-center py-16">
            <Spinner className="size-6" />
          </div>
        ) : isPortfolioError || !portfolio ? (
          <Empty className="border">
            <EmptyHeader>
              <EmptyTitle>Portfolio nicht gefunden</EmptyTitle>
              <EmptyDescription>
                Das Portfolio existiert nicht oder du hast keinen Zugriff.
              </EmptyDescription>
            </EmptyHeader>
            <Link
              href="/portfolios"
              className="inline-flex h-8 items-center justify-center rounded-lg bg-primary px-2.5 text-sm font-medium text-primary-foreground hover:bg-primary/80"
            >
              Zur Übersicht
            </Link>
          </Empty>
        ) : (
          <>
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
                    {isPricesLoading ? (
                      <Spinner className="size-5" />
                    ) : priceUser !== undefined ? (
                      formatPrice(priceUser)
                    ) : (
                      "—"
                    )}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card>
                <CardHeader>
                  <CardDescription>Portfoliowert</CardDescription>
                  <CardTitle className="text-2xl">
                    {isPricesLoading ? (
                      <Spinner className="size-5" />
                    ) : portfolioValue !== undefined ? (
                      formatPrice(portfolioValue)
                    ) : (
                      "—"
                    )}
                  </CardTitle>
                </CardHeader>
              </Card>
              <Card>
                <CardHeader>
                  <CardDescription>Erfolg</CardDescription>
                  <CardTitle className="text-2xl">
                    {isPricesLoading ? (
                      <Spinner className="size-5" />
                    ) : performance !== null ? (
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

              {isMovementsError ? (
                <Empty className="border">
                  <EmptyHeader>
                    <EmptyTitle>
                      Buchungen konnten nicht geladen werden
                    </EmptyTitle>
                    <EmptyDescription>
                      Bitte versuche es später erneut.
                    </EmptyDescription>
                  </EmptyHeader>
                </Empty>
              ) : movements.length === 0 ? (
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
          </>
        )}
      </main>

      {portfolio ? (
        <PortfolioMovementDialog
          portfolioId={portfolioId}
          open={movementDialogOpen}
          onOpenChange={setMovementDialogOpen}
        />
      ) : null}
    </div>
  );
}
