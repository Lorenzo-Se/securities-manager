// Liveness check for the deploy gate and monitoring (pve-iac). No auth.
export const dynamic = "force-dynamic";

export function GET() {
  return new Response("ok");
}
