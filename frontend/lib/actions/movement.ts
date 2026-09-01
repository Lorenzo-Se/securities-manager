"use server";

import { revalidatePath } from "next/cache";

import { actionError, actionSuccess } from "@/lib/actions/errors";
import type { ActionResult } from "@/lib/actions/types";
import type {
  CreatePortfolioMovementBody,
  PortfolioMovement,
} from "@/lib/api/generated/models";
import {
  createPortfolioMovement,
  deletePortfolioMovement,
} from "@/lib/api/generated/portfolio/portfolio";

function revalidatePortfolioPaths(portfolioId: number) {
  revalidatePath("/portfolios");
  revalidatePath(`/portfolios/${portfolioId}`);
}

export async function createPortfolioMovementAction(
  portfolioId: number,
  data: CreatePortfolioMovementBody,
): Promise<ActionResult<PortfolioMovement>> {
  try {
    const response = await createPortfolioMovement(portfolioId, data);

    if (response.status !== 201) {
      return actionError(null, "Buchung konnte nicht gespeichert werden.");
    }

    revalidatePortfolioPaths(portfolioId);

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Buchung konnte nicht gespeichert werden.");
  }
}

export async function deletePortfolioMovementAction(
  portfolioId: number,
  movementId: number,
): Promise<ActionResult> {
  try {
    const response = await deletePortfolioMovement(portfolioId, movementId);

    if (response.status !== 204) {
      return actionError(null, "Buchung konnte nicht gelöscht werden.");
    }

    revalidatePortfolioPaths(portfolioId);

    return actionSuccess();
  } catch (error) {
    return actionError(error, "Buchung konnte nicht gelöscht werden.");
  }
}
