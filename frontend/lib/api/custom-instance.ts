import { getAccessToken } from "./access-token";

const API_BASE_URL =
  process.env.NEXT_PUBLIC_API_URL ?? "http://localhost:8000/api";

export type ErrorType<Error> = Error & {
  status?: number;
  info?: Error;
};

export type BodyType<BodyData> = BodyData;

type HttpMethod = "GET" | "POST" | "PUT" | "DELETE" | "PATCH";

type RequestConfig = {
  method?: HttpMethod;
  params?: Record<string, unknown>;
  body?: unknown;
  headers?: HeadersInit;
  signal?: AbortSignal;
};

const buildUrl = (path: string, params?: Record<string, unknown>): string => {
  const base = API_BASE_URL.replace(/\/$/, "");
  const normalizedPath = path.startsWith("/") ? path : `/${path}`;
  const url = new URL(`${base}${normalizedPath}`);

  if (params) {
    for (const [key, value] of Object.entries(params)) {
      if (value === undefined || value === null) {
        continue;
      }

      url.searchParams.set(key, String(value));
    }
  }

  return url.toString();
};

const serializeBody = (body: unknown): string => {
  if (typeof body === "string") {
    return body;
  }

  return JSON.stringify(body);
};

export const customInstance = async <T>(
  url: string,
  { method, params, body, headers, signal }: RequestConfig,
): Promise<T> => {
  const token = getAccessToken();
  const requestHeaders: Record<string, string> = {
    Accept: "application/json",
  };

  if (token !== null) {
    requestHeaders.Authorization = `Bearer ${token}`;
  }

  if (body !== undefined) {
    requestHeaders["Content-Type"] = "application/json";
  }

  const response = await fetch(buildUrl(url, params), {
    method: method ?? "GET",
    headers: {
      ...requestHeaders,
      ...(headers as Record<string, string> | undefined),
    },
    body: body !== undefined ? serializeBody(body) : undefined,
    signal,
  });

  const contentType = response.headers.get("content-type");
  const data =
    response.status === 204
      ? undefined
      : contentType?.includes("application/json")
        ? await response.json()
        : await response.text();

  if (!response.ok) {
    const error = new Error(response.statusText) as ErrorType<Error>;
    error.status = response.status;
    error.info = data;

    throw error;
  }

  return {
    data,
    status: response.status,
    headers: response.headers,
  } as T;
};

export default customInstance;
