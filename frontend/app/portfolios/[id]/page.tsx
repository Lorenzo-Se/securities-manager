import { PortfolioDetailContent } from "@/components/portfolio-detail-content";

type PortfolioDetailPageProps = {
  params: Promise<{
    id: string;
  }>;
};

export default async function PortfolioDetailPage({
  params,
}: PortfolioDetailPageProps) {
  const { id } = await params;
  const portfolioId = Number(id);

  return <PortfolioDetailContent portfolioId={portfolioId} />;
}
