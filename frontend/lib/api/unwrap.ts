export function unwrap<T>(response: { status: number; data: unknown }): T {
  if (response.status !== 200 && response.status !== 201) {
    throw new Error("API error");
  }

  return response.data as T;
}
