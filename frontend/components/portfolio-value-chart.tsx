"use client";

import { useEffect, useMemo, useState, useTransition } from "react";
import { format, parseISO } from "date-fns";
import { de } from "date-fns/locale";
import { CartesianGrid, Line, LineChart, XAxis, YAxis } from "recharts";

import { getPortfolioValueHistoryAction } from "@/lib/actions/portfolio";
import type { GetPortfolioValueHistoryDays } from "@/lib/api/generated/models";
import type { PortfolioValueHistory } from "@/lib/api/generated/models";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import {
  ChartContainer,
  ChartTooltip,
  type ChartConfig,
} from "@/components/ui/chart";
import {
  Empty,
  EmptyDescription,
  EmptyHeader,
  EmptyTitle,
} from "@/components/ui/empty";
import { Skeleton } from "@/components/ui/skeleton";
import {
  formatProfitPercent,
  getProfitToneClass,
} from "@/lib/portfolio-performance";

type ValueHistoryDays =
  (typeof GetPortfolioValueHistoryDays)[keyof typeof GetPortfolioValueHistoryDays];

const periodOptions: Array<{ label: string; days: ValueHistoryDays }> = [
  { label: "7T", days: 7 },
  { label: "30T", days: 30 },
  { label: "90T", days: 90 },
  { label: "1J", days: 365 },
  { label: "Max", days: "max" },
];

const chartConfig = {
  value: {
    label: "Wert",
    color: "var(--chart-1)",
  },
} satisfies ChartConfig;

type PortfolioValueChartProps = {
  portfolioId: number;
  initialData: PortfolioValueHistory;
  formatPrice: (value: number) => string;
};

function shouldShowTimeInChart(days: ValueHistoryDays): boolean {
  return days === 7 || days === 30;
}

function formatChartDate(value: string, days: ValueHistoryDays): string {
  const date = parseISO(value);

  if (shouldShowTimeInChart(days)) {
    return format(date, "dd.MM. HH:mm", { locale: de });
  }

  return format(date, "dd.MM.yyyy", { locale: de });
}

function formatChartAxisDate(value: string, days: ValueHistoryDays): string {
  const date = parseISO(value);

  if (shouldShowTimeInChart(days)) {
    return format(date, "dd.MM. HH:mm", { locale: de });
  }

  return format(date, "dd.MM.", { locale: de });
}

type ChartPoint = {
  date: string;
  value: number;
  profitAmount: number | null;
  profitPercent: number | null;
};

type ValueHistoryTooltipProps = {
  active?: boolean;
  payload?: Array<{ payload: ChartPoint }>;
  label?: string;
  days: ValueHistoryDays;
  formatPrice: (value: number) => string;
};

function ValueHistoryTooltip({
  active,
  payload,
  label,
  days,
  formatPrice,
}: ValueHistoryTooltipProps) {
  if (!active || !payload?.length || !label) {
    return null;
  }

  const point = payload[0].payload;

  return (
    <div className="grid min-w-36 gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl">
      <div className="font-medium">{formatChartDate(label, days)}</div>
      <div className="flex items-center justify-between gap-4">
        <span className="text-muted-foreground">Wert</span>
        <span className="font-mono font-medium tabular-nums">
          {formatPrice(point.value)}
        </span>
      </div>
      {point.profitAmount !== null && point.profitPercent !== null ? (
        <div className="flex items-center justify-between gap-4">
          <span className="text-muted-foreground">Erfolg</span>
          <span
            className={`font-mono font-medium tabular-nums ${getProfitToneClass(point.profitAmount)}`}
          >
            {formatPrice(point.profitAmount)} (
            {formatProfitPercent(point.profitPercent)})
          </span>
        </div>
      ) : null}
    </div>
  );
}

export function PortfolioValueChart({
  portfolioId,
  initialData,
  formatPrice,
}: PortfolioValueChartProps) {
  const [days, setDays] = useState<ValueHistoryDays>(30);
  const [data, setData] = useState(initialData);
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();

  useEffect(() => {
    if (days === initialData.days) {
      setData(initialData);
      setError(null);
      return;
    }

    startTransition(async () => {
      const result = await getPortfolioValueHistoryAction(portfolioId, days);

      if (!result.ok) {
        setError(result.error);
        return;
      }

      if (result.data) {
        setData(result.data);
      }
      setError(null);
    });
  }, [days, initialData, portfolioId]);

  const chartData = useMemo<ChartPoint[]>(
    () =>
      data.points.map((point) => ({
        date: point.date,
        value: Number(point.value),
        profitAmount:
          point.profit_amount === null || point.profit_amount === undefined
            ? null
            : Number(point.profit_amount),
        profitPercent:
          point.profit_percent === null || point.profit_percent === undefined
            ? null
            : Number(point.profit_percent),
      })),
    [data.points],
  );

  const yDomain = useMemo(() => {
    if (chartData.length === 0) {
      return undefined;
    }

    const values = chartData.map((point) => point.value);
    const min = Math.min(...values);
    const max = Math.max(...values);
    const spread = max - min;
    const padding = spread > 0 ? spread * 0.1 : Math.max(max * 0.1, 1);

    return [min - padding, max + padding] as [number, number];
  }, [chartData]);

  const yAxisFormatter = (value: number) =>
    new Intl.NumberFormat("de-DE", {
      notation: "compact",
      maximumFractionDigits: 1,
    }).format(value);

  return (
    <Card className="mb-8">
      <CardHeader className="flex flex-row flex-wrap items-center justify-between gap-4 space-y-0">
        <CardTitle>Wertverlauf</CardTitle>
        <div className="flex flex-wrap gap-1">
          {periodOptions.map((option) => (
            <Button
              key={option.label}
              type="button"
              size="sm"
              variant={days === option.days ? "default" : "outline"}
              onClick={() => setDays(option.days)}
              disabled={isPending}
            >
              {option.label}
            </Button>
          ))}
        </div>
      </CardHeader>
      <CardContent>
        {error ? (
          <p className="text-sm text-destructive">{error}</p>
        ) : isPending ? (
          <Skeleton className="h-[300px] w-full" />
        ) : chartData.length === 0 ? (
          <Empty className="border">
            <EmptyHeader>
              <EmptyTitle>Noch keine Transaktionsdaten</EmptyTitle>
              <EmptyDescription>
                Der Wertverlauf wird angezeigt, sobald Transaktionen
                synchronisiert wurden.
              </EmptyDescription>
            </EmptyHeader>
          </Empty>
        ) : (
          <ChartContainer config={chartConfig} className="h-[300px] w-full">
            <LineChart
              data={chartData}
              margin={{ top: 8, right: 8, left: 0, bottom: 0 }}
            >
              <CartesianGrid vertical={false} />
              <XAxis
                dataKey="date"
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                minTickGap={32}
                tickFormatter={(value) =>
                  formatChartAxisDate(String(value), days)
                }
              />
              <YAxis
                tickLine={false}
                axisLine={false}
                tickMargin={8}
                width={56}
                domain={yDomain}
                tickFormatter={yAxisFormatter}
              />
              <ChartTooltip
                content={
                  <ValueHistoryTooltip days={days} formatPrice={formatPrice} />
                }
              />
              <Line
                type="monotone"
                dataKey="value"
                stroke="var(--color-value)"
                strokeWidth={2}
                dot={false}
              />
            </LineChart>
          </ChartContainer>
        )}
      </CardContent>
    </Card>
  );
}
