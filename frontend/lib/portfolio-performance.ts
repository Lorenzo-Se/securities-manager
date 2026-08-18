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
