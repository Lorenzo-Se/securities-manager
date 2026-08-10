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

export const customInstance = async <T>(
  url: string,
  { method, params, body, headers, signal }: RequestConfig,
): Promise<T> => {
  const response = await fetch(buildUrl(url, params), {
    method: method ?? "GET",
    headers: {
      Accept: "application/json",
      ...(body !== undefined ? { "Content-Type": "application/json" } : {}),
      ...headers,
    },
    body: body !== undefined ? JSON.stringify(body) : undefined,
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
