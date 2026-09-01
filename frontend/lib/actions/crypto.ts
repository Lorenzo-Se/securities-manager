"use server";

import { actionError, actionSuccess } from "@/lib/actions/errors";
import type { ActionResult } from "@/lib/actions/types";
import type { CryptoCoin } from "@/lib/api/generated/models";
import { listCryptoCoins } from "@/lib/api/generated/crypto/crypto";

export async function searchCryptoCoinsAction(
  search: string,
): Promise<ActionResult<CryptoCoin[]>> {
  try {
    const response = await listCryptoCoins(
      search.trim() ? { search: search.trim() } : undefined,
    );

    if (response.status !== 200) {
      return actionError(null, "Kryptowährungen konnten nicht geladen werden.");
    }

    return actionSuccess(response.data);
  } catch (error) {
    return actionError(error, "Kryptowährungen konnten nicht geladen werden.");
  }
}
