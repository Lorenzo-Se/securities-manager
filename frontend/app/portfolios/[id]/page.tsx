import { PortfolioDetailContent } from "@/components/portfolio-detail-content";
import { getCryptoPrices } from "@/lib/api/generated/crypto/crypto";
import type {
  CryptoPrices,
  GetAuthenticatedUser200,
  Portfolio,
  PortfolioMovement,
} from "@/lib/api/generated/models";
import {
  getPortfolio,
  listPortfolioMovements,
} from "@/lib/api/generated/portfolio/portfolio";
import { getAuthenticatedUser } from "@/lib/api/generated/user/user";
import { unwrap } from "@/lib/api/unwrap";

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

  const portfolio = await getPortfolio(portfolioId).then((response) =>
    unwrap<Portfolio>(response),
  );

  const [user, movements, prices] = await Promise.all([
    getAuthenticatedUser().then((response) =>
      unwrap<GetAuthenticatedUser200>(response),
    ),
    listPortfolioMovements(portfolioId).then((response) =>
      unwrap<PortfolioMovement[]>(response),
    ),
    getCryptoPrices({ ids: portfolio.crypto_id }).then((response) =>
      unwrap<CryptoPrices>(response),
    ),
  ]);

  return (
    <PortfolioDetailContent
      portfolioId={portfolioId}
      portfolio={portfolio}
      user={user}
      movements={movements}
      prices={prices}
    />
  );
}
