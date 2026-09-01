import type { ErrorType } from "@/lib/api/custom-instance";

import type { ActionResult } from "./types";

type ValidationErrorBody = {
  message?: string;
  errors?: Record<string, string[]>;
};

export function actionError(
  error: unknown,
  fallbackMessage: string,
): ActionResult<never> {
  if (error instanceof Error) {
    const apiError = error as ErrorType<ValidationErrorBody>;
    const info = apiError.info;

    if (info && typeof info === "object" && "errors" in info && info.errors) {
      return {
        ok: false,
        error: info.message ?? fallbackMessage,
        fieldErrors: info.errors,
      };
    }

    return {
      ok: false,
      error: error.message || fallbackMessage,
    };
  }

  return { ok: false, error: fallbackMessage };
}

export function actionSuccess(): ActionResult;
export function actionSuccess<T>(data: T): ActionResult<T>;
export function actionSuccess<T>(data?: T): ActionResult<T> {
  if (data === undefined) {
    return { ok: true };
  }

  return { ok: true, data };
}
