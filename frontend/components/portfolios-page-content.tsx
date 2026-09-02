"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { Trash2Icon, PencilIcon, WalletIcon } from "lucide-react";

import { deletePortfolioAction } from "@/lib/actions/portfolio";
import { PortfolioCreateDialog } from "@/components/portfolio-create-dialog";
import { PortfolioEditDialog } from "@/components/portfolio-edit-dialog";
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
import type {
  CryptoPrices,
  GetAuthenticatedUser200,
  Portfolio,
} from "@/lib/api/generated/models";
import { createPriceFormatter } from "@/lib/format-price";
import {
  calculatePortfolioPerformance,
  formatProfitPercent,
  getProfitToneClass,
} from "@/lib/portfolio-performance";

type PortfoliosPageContentProps = {
  portfolios: Portfolio[];
  user: GetAuthenticatedUser200;
  prices: CryptoPrices | null;
};

function PortfolioCard({
  portfolio,
  priceUser,
  priceUsd,
  formatPrice,
}: {
  portfolio: Portfolio;
  priceUser?: number;
  priceUsd?: number;
  formatPrice: (value: number) => string;
}) {
  const [isDeleting, setIsDeleting] = useState(false);
  const [editDialogOpen, setEditDialogOpen] = useState(false);
  const [isPending, startTransition] = useTransition();

  const performance = calculatePortfolioPerformance({
    balance: Number(portfolio.balance),
    costBasisUsd:
      portfolio.cost_basis_usd !== null
        ? Number(portfolio.cost_basis_usd)
        : null,
    priceUser,
    priceUsd,
  });

  const handleDelete = () => {
    setIsDeleting(true);
    startTransition(async () => {
      const result = await deletePortfolioAction(portfolio.id);
      if (!result.ok) {
        setIsDeleting(false);
      }
    });
  };

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
                disabled={isDeleting || isPending}
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
              <AlertDialogAction variant="destructive" onClick={handleDelete}>
                Löschen
              </AlertDialogAction>
            </AlertDialogFooter>
          </AlertDialogContent>
        </AlertDialog>
        <Button
          variant="outline"
          size="icon-sm"
          onClick={() => setEditDialogOpen(true)}
        >
          <PencilIcon className="size-4" />
          <span className="sr-only">Portfolio bearbeiten</span>
        </Button>
      </CardHeader>
      <CardContent className="space-y-4">
        <div>
          <p className="text-sm text-muted-foreground">Portfoliowert</p>
          <p className="text-2xl font-semibold tracking-tight">
            {performance !== null ? formatPrice(performance.portfolioValue) : "—"}
          </p>
        </div>
        <div>
          <p className="text-sm text-muted-foreground">Erfolg</p>
          {performance !== null ? (
            <div className="space-y-1">
              <p
                className={`text-2xl font-semibold tracking-tight ${getProfitToneClass(performance.profitAmount)}`}
              >
                {formatProfitPercent(performance.profitPercent)}
              </p>
              <p
                className={`text-sm font-medium ${getProfitToneClass(performance.profitAmount)}`}
              >
                {formatPrice(performance.profitAmount)}
              </p>
            </div>
          ) : (
            <p className="text-2xl font-semibold tracking-tight">—</p>
          )}
        </div>
        <Link
          href={`/portfolios/${portfolio.id}`}
          className="inline-flex h-7 w-full items-center justify-center rounded-[min(var(--radius-md),12px)] border border-border bg-background px-2.5 text-sm font-medium hover:bg-muted hover:text-foreground"
        >
          Transaktionen ansehen
        </Link>
      </CardContent>
      <PortfolioEditDialog
        portfolio={portfolio}
        open={editDialogOpen}
        onOpenChange={setEditDialogOpen}
      />
    </Card>
  );
}

export function PortfoliosPageContent({
  portfolios,
  user,
  prices,
}: PortfoliosPageContentProps) {
  const [createDialogOpen, setCreateDialogOpen] = useState(false);

  const formatPrice = useMemo(
    () => createPriceFormatter(user.currency).format,
    [user.currency],
  );

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
              Erstelle Portfolios mit automatischem On-Chain-Transaktionsimport.
            </p>
          </div>
          <Button onClick={() => setCreateDialogOpen(true)}>
            Portfolio erstellen
          </Button>
        </div>

        {portfolios.length === 0 ? (
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
                priceUser={prices?.prices[portfolio.crypto_id]}
                priceUsd={prices?.reference_prices[portfolio.crypto_id]}
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
