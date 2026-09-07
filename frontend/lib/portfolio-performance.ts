type CalculatePortfolioPerformanceInput = {
  balance: number;
  costBasisUsd: number | null | undefined;
  priceUser: number | undefined;
  priceUsd: number | undefined;
};

export type PortfolioPerformance = {
  portfolioValue: number;
  profitAmount: number;
  profitPercent: number;
};

export function getEffectiveBalance(portfolio: {
  balance?: string | null;
  share?: {
    my_share_amount?: string | null;
    type?: string;
  } | null;
}): number | null {
  if (
    portfolio.share?.my_share_amount !== null &&
    portfolio.share?.my_share_amount !== undefined
  ) {
    return Number(portfolio.share.my_share_amount);
  }

  if (portfolio.balance === null || portfolio.balance === undefined) {
    return null;
  }

  return Number(portfolio.balance);
}

export function getEffectiveCostBasisUsd(portfolio: {
  balance?: string | null;
  cost_basis_usd?: string | null;
  share?: {
    my_share_amount?: string | null;
    my_share_cost_basis_usd?: string | null;
    amount?: string | null;
    percentage?: string | null;
    type?: string;
  } | null;
}): number | null {
  if (
    portfolio.share?.my_share_cost_basis_usd !== null &&
    portfolio.share?.my_share_cost_basis_usd !== undefined
  ) {
    return Number(portfolio.share.my_share_cost_basis_usd);
  }

  if (portfolio.cost_basis_usd === null || portfolio.cost_basis_usd === undefined) {
    return null;
  }

  const totalCostBasis = Number(portfolio.cost_basis_usd);

  if (!portfolio.share || portfolio.share.type === "viewer") {
    return totalCostBasis;
  }

  if (portfolio.share.type === "percentage" && portfolio.share.percentage) {
    return totalCostBasis * (Number(portfolio.share.percentage) / 100);
  }

  if (
    portfolio.share.type === "amount" &&
    portfolio.share.amount &&
    portfolio.balance
  ) {
    const totalBalance = Number(portfolio.balance);

    if (totalBalance <= 0) {
      return null;
    }

    return totalCostBasis * (Number(portfolio.share.amount) / totalBalance);
  }

  return totalCostBasis;
}

export function shouldShowTotalPortfolioValue(portfolio: {
  access_role: string;
  share?: {
    show_total_balance?: boolean;
    type?: string;
  } | null;
}): boolean {
  if (portfolio.access_role === "owner") {
    return true;
  }

  if (!portfolio.share || portfolio.share.type === "viewer") {
    return true;
  }

  return portfolio.share.show_total_balance === true;
}

export function shouldShowMovements(portfolio: {
  access_role: string;
  share?: {
    show_total_balance?: boolean;
    type?: string;
  } | null;
}): boolean {
  if (portfolio.access_role === "owner") {
    return true;
  }

  if (!portfolio.share || portfolio.share.type === "viewer") {
    return true;
  }

  return portfolio.share.show_total_balance === true;
}

export function calculateShareValue(
  balance: number,
  priceUser: number | undefined,
): number | null {
  if (priceUser === undefined) {
    return null;
  }

  return balance * priceUser;
}

export function calculatePortfolioPerformance({
  balance,
  costBasisUsd,
  priceUser,
  priceUsd,
}: CalculatePortfolioPerformanceInput): PortfolioPerformance | null {
  if (
    costBasisUsd === null ||
    costBasisUsd === undefined ||
    priceUser === undefined ||
    priceUsd === undefined ||
    priceUsd <= 0
  ) {
    return null;
  }

  const portfolioValue = balance * priceUser;
  const costBasisUser = costBasisUsd * (priceUser / priceUsd);
  const profitAmount = portfolioValue - costBasisUser;
  const profitPercent =
    costBasisUser > 0 ? (profitAmount / costBasisUser) * 100 : 0;

  return {
    portfolioValue,
    profitAmount,
    profitPercent,
  };
}

const percentFormatter = new Intl.NumberFormat("de-DE", {
  minimumFractionDigits: 2,
  maximumFractionDigits: 2,
  signDisplay: "exceptZero",
});

export function formatProfitPercent(value: number): string {
  return `${percentFormatter.format(value)} %`;
}

export function getProfitToneClass(value: number): string {
  if (value > 0) {
    return "text-emerald-600 dark:text-emerald-400";
  }

  if (value < 0) {
    return "text-red-600 dark:text-red-400";
  }

  return "text-muted-foreground";
}
