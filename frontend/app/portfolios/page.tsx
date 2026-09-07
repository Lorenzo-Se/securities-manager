import { PortfoliosPageContent } from "@/components/portfolios-page-content";
import { getCryptoPrices } from "@/lib/api/generated/crypto/crypto";
import type {
  CryptoPrices,
  GetAuthenticatedUser200,
  Portfolio,
  PortfolioShareInvitation,
} from "@/lib/api/generated/models";
import {
  listPortfolioShareInvitations,
  listPortfolios,
} from "@/lib/api/generated/portfolio/portfolio";
import { getAuthenticatedUser } from "@/lib/api/generated/user/user";
import { unwrap } from "@/lib/api/unwrap";

export default async function PortfoliosPage() {
  const [portfolios, user, invitations] = await Promise.all([
    listPortfolios().then((response) => unwrap<Portfolio[]>(response)),
    getAuthenticatedUser().then((response) =>
      unwrap<GetAuthenticatedUser200>(response),
    ),
    listPortfolioShareInvitations()
      .then((response) => unwrap<PortfolioShareInvitation[]>(response))
      .catch(() => [] as PortfolioShareInvitation[]),
  ]);

  const cryptoIds = portfolios.map((portfolio) => portfolio.crypto_id).join(",");
  const prices =
    cryptoIds.length > 0
      ? await getCryptoPrices({ ids: cryptoIds }).then((response) =>
          unwrap<CryptoPrices>(response),
        )
      : null;

  return (
    <PortfoliosPageContent
      portfolios={portfolios}
      user={user}
      prices={prices}
      invitations={invitations}
    />
  );
}
