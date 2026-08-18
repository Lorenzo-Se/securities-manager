import { auth } from "@/auth";
import { NextResponse } from "next/server";

export default auth((request) => {
  const protectedPaths = ["/portfolios", "/settings"];

  if (
    !request.auth &&
    protectedPaths.some((path) => request.nextUrl.pathname.startsWith(path))
  ) {
    const signInUrl = new URL("/api/auth/signin", request.nextUrl.origin);
    signInUrl.searchParams.set("callbackUrl", request.nextUrl.pathname);

    return NextResponse.redirect(signInUrl);
  }
});

export const config = {
  matcher: ["/portfolios/:path*", "/settings"],
};
