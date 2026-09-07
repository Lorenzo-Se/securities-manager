"use client";

import { useMemo, useState, useTransition } from "react";
import Link from "next/link";
import { Trash2Icon, PencilIcon, WalletIcon } from "lucide-react";

import { deletePortfolioAction } from "@/lib/actions/portfolio";
import { PortfolioCreateDialog } from "@/components/portfolio-create-dialog";
import { PortfolioEditDialog } from "@/components/portfolio-edit-dialog";
import { PortfolioInvitationsBanner } from "@/components/portfolio-invitations-banner";
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
  PortfolioShareInvitation,
} from "@/lib/api/generated/models";
import { createPriceFormatter } from "@/lib/format-price";
import {
  calculatePortfolioPerformance,
  calculateShareValue,
  formatProfitPercent,
  getEffectiveBalance,
  getEffectiveCostBasisUsd,
  getProfitToneClass,
  shouldShowTotalPortfolioValue,
} from "@/lib/portfolio-performance";

type PortfoliosPageContentProps = {
  portfolios: Portfolio[];
  user: GetAuthenticatedUser200;
  prices: CryptoPrices | null;
  invitations: PortfolioShareInvitation[];
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

  const isOwner = portfolio.access_role === "owner";
  const showTotal = shouldShowTotalPortfolioValue(portfolio);
  const effectiveBalance = getEffectiveBalance(portfolio);
  const effectiveCostBasis = getEffectiveCostBasisUsd(portfolio);

  const performance =
    effectiveBalance !== null
      ? calculatePortfolioPerformance({
          balance: effectiveBalance,
          costBasisUsd: effectiveCostBasis,
          priceUser,
          priceUsd,
        })
      : null;

  const shareValue =
    effectiveBalance !== null
      ? calculateShareValue(effectiveBalance, priceUser)
      : null;

  const displayValue = shareValue ?? performance?.portfolioValue ?? null;

  const handleDelete = () => {
    setIsDeleting(true);
    startTransition(async () => {
      const result = await deletePortfolioAction(portfolio.id);
      if (!result.ok) {
        setIsDeleting(false);
      }
    });
  };

  const valueLabel =
    !showTotal && portfolio.share?.my_share_amount
      ? "Wert deines Anteils"
      : "Portfoliowert";

  return (
    <Card className="transition-colors hover:border-foreground/20">
      <CardHeader className="flex flex-row items-start justify-between gap-4">
        <Link href={`/portfolios/${portfolio.id}`} className="min-w-0 flex-1 space-y-1">
          <div className="flex flex-wrap items-center gap-2">
            <CardTitle className="hover:underline">{portfolio.name}</CardTitle>
            {!isOwner ? <Badge variant="outline">Geteilt</Badge> : null}
          </div>
          <CardDescription>
            <Badge variant="secondary" className="uppercase">
              {portfolio.crypto_symbol}
            </Badge>{" "}
            {portfolio.crypto_name}
          </CardDescription>
        </Link>

        {isOwner ? (
          <>
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
          </>
        ) : null}
      </CardHeader>
      <CardContent className="space-y-4">
        {portfolio.share?.my_share_amount ? (
          <div>
            <p className="text-sm text-muted-foreground">Dein Anteil</p>
            <p className="text-lg font-semibold tracking-tight">
              {portfolio.share.my_share_amount}{" "}
              <span className="text-sm uppercase text-muted-foreground">
                {portfolio.crypto_symbol}
              </span>
            </p>
          </div>
        ) : null}
        <div>
          <p className="text-sm text-muted-foreground">{valueLabel}</p>
          <p className="text-2xl font-semibold tracking-tight">
            {displayValue !== null ? formatPrice(displayValue) : "—"}
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
      {isOwner ? (
        <PortfolioEditDialog
          portfolio={portfolio}
          open={editDialogOpen}
          onOpenChange={setEditDialogOpen}
        />
      ) : null}
    </Card>
  );
}

export function PortfoliosPageContent({
  portfolios,
  user,
  prices,
  invitations,
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
        <PortfolioInvitationsBanner invitations={invitations} />

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
