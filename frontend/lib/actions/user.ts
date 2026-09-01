"use server";

import { revalidatePath } from "next/cache";

import { actionError, actionSuccess } from "@/lib/actions/errors";
import type { ActionResult } from "@/lib/actions/types";
import type { GetAuthenticatedUser200 } from "@/lib/api/generated/models";
import { updateAuthenticatedUser } from "@/lib/api/generated/user/user";

function revalidateCurrencyPaths() {
  revalidatePath("/settings");
  revalidatePath("/portfolios");
  revalidatePath("/portfolios", "layout");
}

export async function updateUserCurrencyAction(
  currency: string,
): Promise<ActionResult<GetAuthenticatedUser200>> {
  try {
    const response = await updateAuthenticatedUser({ currency });

    if (response.status !== 200) {
      return actionError(null, "Währung konnte nicht gespeichert werden.");
    }

    revalidateCurrencyPaths();

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Währung konnte nicht gespeichert werden.");
  }
}
