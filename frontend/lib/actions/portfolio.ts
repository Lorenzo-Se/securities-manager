"use server";

import { revalidatePath } from "next/cache";

import { actionError, actionSuccess } from "@/lib/actions/errors";
import type { ActionResult } from "@/lib/actions/types";
import type {
  CreatePortfolioBody,
  GetPortfolioValueHistoryDays,
  Portfolio,
  PortfolioValueHistory,
  UpdatePortfolioBody,
} from "@/lib/api/generated/models";
import {
  createPortfolio,
  deletePortfolio,
  getPortfolioValueHistory,
  syncPortfolio,
  updatePortfolio,
} from "@/lib/api/generated/portfolio/portfolio";

export async function createPortfolioAction(
  data: CreatePortfolioBody,
): Promise<ActionResult<Portfolio>> {
  try {
    const response = await createPortfolio(data);

    if (response.status !== 201) {
      return actionError(null, "Portfolio konnte nicht erstellt werden.");
    }

    revalidatePath("/portfolios");

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Portfolio konnte nicht erstellt werden.");
  }
}

export async function updatePortfolioAction(
  portfolioId: number,
  data: UpdatePortfolioBody,
): Promise<ActionResult<Portfolio>> {
  try {
    const response = await updatePortfolio(portfolioId, data);

    if (response.status !== 200) {
      return actionError(null, "Portfolio konnte nicht aktualisiert werden.");
    }

    revalidatePath("/portfolios");
    revalidatePath(`/portfolios/${portfolioId}`);

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Portfolio konnte nicht aktualisiert werden.");
  }
}

export async function syncPortfolioAction(
  portfolioId: number,
): Promise<ActionResult<Portfolio>> {
  try {
    const response = await syncPortfolio(portfolioId);

    if (response.status !== 202) {
      return actionError(null, "Synchronisation konnte nicht gestartet werden.");
    }

    revalidatePath("/portfolios");
    revalidatePath(`/portfolios/${portfolioId}`);

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Synchronisation konnte nicht gestartet werden.");
  }
}

export async function getPortfolioValueHistoryAction(
  portfolioId: number,
  days: (typeof GetPortfolioValueHistoryDays)[keyof typeof GetPortfolioValueHistoryDays],
): Promise<ActionResult<PortfolioValueHistory>> {
  try {
    const response = await getPortfolioValueHistory(portfolioId, { days });

    if (response.status !== 200) {
      const message =
        response.status === 503
          ? "Marktdaten sind derzeit nicht verfügbar. Bitte versuche es später erneut."
          : "Wertverlauf konnte nicht geladen werden.";

      return actionError(null, message);
    }

    return actionSuccess(response.data);
  } catch {
    return actionError(
      null,
      "Marktdaten sind derzeit nicht verfügbar. Bitte versuche es später erneut.",
    );
  }
}

export async function deletePortfolioAction(
  portfolioId: number,
): Promise<ActionResult> {
  try {
    const response = await deletePortfolio(portfolioId);

    if (response.status !== 204) {
      return actionError(null, "Portfolio konnte nicht gelöscht werden.");
    }

    revalidatePath("/portfolios");

    return actionSuccess();
  } catch (error) {
    return actionError(error, "Portfolio konnte nicht gelöscht werden.");
  }
}
