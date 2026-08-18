"use client";

import { useMemo, useState } from "react";
import Link from "next/link";
import { useQueryClient } from "@tanstack/react-query";
import { useSession } from "next-auth/react";
import { Trash2Icon, WalletIcon } from "lucide-react";

import { PortfolioCreateDialog } from "@/components/portfolio-create-dialog";
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
  EmptyMedia,
  EmptyTitle,
} from "@/components/ui/empty";
import { Spinner } from "@/components/ui/spinner";
import { useGetCryptoPrices } from "@/lib/api/generated/crypto/crypto";
import {
  getListPortfoliosQueryKey,
  useDeletePortfolio,
  useListPortfolios,
} from "@/lib/api/generated/portfolio/portfolio";
import { useGetAuthenticatedUser } from "@/lib/api/generated/user/user";
import type { Portfolio } from "@/lib/api/generated/models";
import { useApiEnabled } from "@/hooks/use-api-enabled";
import { createPriceFormatter } from "@/lib/format-price";

const amountFormatter = new Intl.NumberFormat("de-DE", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 8,
});

function PortfolioCard({
  portfolio,
  price,
  isPriceLoading,
  formatPrice,
}: {
  portfolio: Portfolio;
  price?: number;
  isPriceLoading: boolean;
  formatPrice: (value: number) => string;
}) {
  const queryClient = useQueryClient();
  const [isDeleting, setIsDeleting] = useState(false);

  const deletePortfolio = useDeletePortfolio({
    mutation: {
      onSuccess: async () => {
        await queryClient.invalidateQueries({
          queryKey: getListPortfoliosQueryKey(),
        });
        setIsDeleting(false);
      },
      onError: () => {
        setIsDeleting(false);
      },
    },
  });

  return (
    <Card className="transition-colors hover:border-foreground/20">
      <CardHeader className="flex flex-row items-start justify-between gap-4">
        <Link href={`/portfolios/${portfolio.id}`} className="min-w-0 flex-1 space-y-1">
          <CardTitle className="hover:underline">{portfolio.name}</CardTitle>
          <CardDescription>
            <Badge variant="secondary" className="uppercase">
              {portfolio.crypto_symbol}
            </Badge>{" "}
            {portfolio.crypto_name}
          </CardDescription>
        </Link>

        <AlertDialog>
          <AlertDialogTrigger
            render={
              <Button
                variant="outline"
                size="icon-sm"
                disabled={isDeleting || deletePortfolio.isPending}
              />
            }
          >
            <Trash2Icon className="size-4" />
            <span className="sr-only">Portfolio löschen</span>
          </AlertDialogTrigger>
          <AlertDialogContent>
            <AlertDialogHeader>
              <AlertDialogTitle>Portfolio löschen?</AlertDialogTitle>
              <AlertDialogDescription>
                „{portfolio.name}“ wird dauerhaft gelöscht.
              </AlertDialogDescription>
            </AlertDialogHeader>
            <AlertDialogFooter>
              <AlertDialogCancel>Abbrechen</AlertDialogCancel>
              <AlertDialogAction
                variant="destructive"
                onClick={() => {
                  setIsDeleting(true);
                  deletePortfolio.mutate({ portfolio: portfolio.id });
                }}
              >
                Löschen
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <p className="text-sm text-muted-foreground">Bestand</p>
          <p className="text-2xl font-semibold tracking-tight">
            {amountFormatter.format(Number(portfolio.balance))}{" "}
            <span className="text-base font-medium uppercase text-muted-foreground">
              {portfolio.crypto_symbol}
            </span>
          </p>
        </div>
        <div>
          <p className="text-sm text-muted-foreground">Aktueller Preis</p>
          <p className="text-2xl font-semibold tracking-tight">
            {isPriceLoading ? (
              <Spinner className="size-5" />
            ) : price !== undefined ? (
              formatPrice(price)
            ) : (
              "—"
            )}
          </p>
        </div>
        <Link
          href={`/portfolios/${portfolio.id}`}
          className="inline-flex h-7 w-full items-center justify-center rounded-[min(var(--radius-md),12px)] border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted hover:text-foreground"
        >
          Buchungen verwalten
        </Link>
      </CardContent>
    </Card>
  );
}

export function PortfoliosPageContent() {
  const [createDialogOpen, setCreateDialogOpen] = useState(false);
  const apiEnabled = useApiEnabled();
  const { status: sessionStatus } = useSession();

  const {
    data: portfoliosResponse,
    isLoading: isLoadingPortfolios,
    isError,
  } = useListPortfolios({
    query: {
      enabled: apiEnabled,
    },
  });

  const { data: userResponse } = useGetAuthenticatedUser({
    query: {
      enabled: apiEnabled,
    },
  });

  const userCurrency =
    userResponse?.status === 200 ? userResponse.data.currency : "usd";

  const portfolios =
    portfoliosResponse?.status === 200 ? portfoliosResponse.data : [];

  const cryptoIds = useMemo(
    () => portfolios.map((portfolio) => portfolio.crypto_id).join(","),
    [portfolios],
  );

  const { data: pricesResponse, isLoading: isPricesLoading } =
    useGetCryptoPrices(
      { ids: cryptoIds },
      {
        query: {
          enabled: apiEnabled && cryptoIds.length > 0,
        },
      },
    );

  const pricesData =
    pricesResponse?.status === 200 ? pricesResponse.data : undefined;

  const formatPrice = useMemo(
    () => createPriceFormatter(userCurrency).format,
    [userCurrency],
  );

  const isLoading =
    sessionStatus === "loading" || (apiEnabled && isLoadingPortfolios);

  return (
    <div className="min-h-full bg-zinc-50 dark:bg-black">
      <header className="border-b border-border bg-background">
        <div className="mx-auto flex w-full max-w-5xl items-center justify-between gap-4 px-6 py-4">
          <div>
            <h1 className="text-xl font-semibold tracking-tight">
              Securities Manager
            </h1>
            <p className="text-sm text-muted-foreground">
              Deine Krypto-Portfolios
            </p>
          </div>
          <AuthButton />
        </div>
      </header>

      <main className="mx-auto w-full max-w-5xl px-6 py-8">
        <div className="mb-6 flex items-center justify-between gap-4">
          <div>
            <h2 className="text-lg font-medium">Portfolios</h2>
            <p className="text-sm text-muted-foreground">
              Erstelle mehrere Portfolios mit Live-Preisen von CoinGecko.
            </p>
          </div>
          <Button onClick={() => setCreateDialogOpen(true)}>
            Portfolio erstellen
          </Button>
        </div>

        {isLoading ? (
          <div className="flex items-center justify-center py-16">
            <Spinner className="size-6" />
          </div>
        ) : isError ? (
          <Empty className="border">
            <EmptyHeader>
              <EmptyTitle>Portfolios konnten nicht geladen werden</EmptyTitle>
              <EmptyDescription>
                Bitte versuche es später erneut.
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : portfolios.length === 0 ? (
          <Empty className="border">
            <EmptyHeader>
              <EmptyMedia variant="icon">
                <WalletIcon />
              </EmptyMedia>
              <EmptyTitle>Noch keine Portfolios</EmptyTitle>
              <EmptyDescription>
                Lege dein erstes Portfolio an und wähle eine Kryptowährung aus.
              </EmptyDescription>
            </EmptyHeader>
            <Button onClick={() => setCreateDialogOpen(true)}>
              Portfolio erstellen
            </Button>
          </Empty>
        ) : (
          <div className="grid gap-4 md:grid-cols-2">
            {portfolios.map((portfolio) => (
              <PortfolioCard
                key={portfolio.id}
                portfolio={portfolio}
                price={pricesData?.prices[portfolio.crypto_id]}
                isPriceLoading={isPricesLoading}
                formatPrice={formatPrice}
              />
            ))}
          </div>
        )}
      </main>

      <PortfolioCreateDialog
        open={createDialogOpen}
        onOpenChange={setCreateDialogOpen}
      />
    </div>
  );
}
