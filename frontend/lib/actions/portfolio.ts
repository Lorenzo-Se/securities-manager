"use server";

import { revalidatePath } from "next/cache";

import { actionError, actionSuccess } from "@/lib/actions/errors";
import type { ActionResult } from "@/lib/actions/types";
import type { CreatePortfolioBody, Portfolio } from "@/lib/api/generated/models";
import {
  createPortfolio,
  deletePortfolio,
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
