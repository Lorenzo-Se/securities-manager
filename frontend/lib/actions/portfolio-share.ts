"use server";

import { revalidatePath } from "next/cache";

import { actionError, actionSuccess } from "@/lib/actions/errors";
import type { ActionResult } from "@/lib/actions/types";
import type {
  CreatePortfolioShareBody,
  PortfolioShare,
  PortfolioShareInvitation,
} from "@/lib/api/generated/models";
import {
  acceptPortfolioShare,
  createPortfolioShare,
  declinePortfolioShare,
  revokePortfolioShare,
} from "@/lib/api/generated/portfolio/portfolio";

export async function createPortfolioShareAction(
  portfolioId: number,
  data: CreatePortfolioShareBody,
): Promise<ActionResult<PortfolioShare>> {
  try {
    const response = await createPortfolioShare(portfolioId, data);

    if (response.status !== 201) {
      return actionError(null, "Einladung konnte nicht erstellt werden.");
    }

    revalidatePath(`/portfolios/${portfolioId}`);

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Einladung konnte nicht erstellt werden.");
  }
}

export async function revokePortfolioShareAction(
  portfolioId: number,
  shareId: number,
): Promise<ActionResult> {
  try {
    const response = await revokePortfolioShare(portfolioId, shareId);

    if (response.status !== 204) {
      return actionError(null, "Freigabe konnte nicht widerrufen werden.");
    }

    revalidatePath(`/portfolios/${portfolioId}`);

    return actionSuccess();
  } catch (error) {
    return actionError(error, "Freigabe konnte nicht widerrufen werden.");
  }
}

export async function acceptPortfolioShareAction(
  shareId: number,
): Promise<ActionResult<PortfolioShareInvitation>> {
  try {
    const response = await acceptPortfolioShare(shareId);

    if (response.status !== 200) {
      return actionError(null, "Einladung konnte nicht angenommen werden.");
    }

    revalidatePath("/portfolios");

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Einladung konnte nicht angenommen werden.");
  }
}

export async function declinePortfolioShareAction(
  shareId: number,
): Promise<ActionResult<PortfolioShareInvitation>> {
  try {
    const response = await declinePortfolioShare(shareId);

    if (response.status !== 200) {
      return actionError(null, "Einladung konnte nicht abgelehnt werden.");
    }

    revalidatePath("/portfolios");

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Einladung konnte nicht abgelehnt werden.");
  }
}
