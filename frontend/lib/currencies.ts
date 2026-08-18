export const SUPPORTED_CURRENCIES = [
  { code: "eur", label: "Euro (EUR)" },
  { code: "usd", label: "US-Dollar (USD)" },
  { code: "chf", label: "Schweizer Franken (CHF)" },
  { code: "gbp", label: "Britisches Pfund (GBP)" },
  { code: "jpy", label: "Japanischer Yen (JPY)" },
  { code: "cad", label: "Kanadischer Dollar (CAD)" },
  { code: "aud", label: "Australischer Dollar (AUD)" },
  { code: "sek", label: "Schwedische Krone (SEK)" },
  { code: "nok", label: "Norwegische Krone (NOK)" },
  { code: "dkk", label: "Dänische Krone (DKK)" },
  { code: "pln", label: "Polnischer Złoty (PLN)" },
  { code: "czk", label: "Tschechische Krone (CZK)" },
  { code: "huf", label: "Ungarischer Forint (HUF)" },
  { code: "brl", label: "Brasilianischer Real (BRL)" },
  { code: "inr", label: "Indische Rupie (INR)" },
] as const;

export type SupportedCurrencyCode = (typeof SUPPORTED_CURRENCIES)[number]["code"];

export function getCurrencyLabel(code: string): string {
  const currency = SUPPORTED_CURRENCIES.find(
    (entry) => entry.code === code.toLowerCase(),
  );

  return currency?.label ?? code.toUpperCase();
}
