import { auth } from "@/auth";
import { hasSessionAuthError } from "@/lib/auth/session-error";
import { NextResponse } from "next/server";

export default auth((request) => {
  const protectedPaths = ["/portfolios", "/settings"];
  const isProtected = protectedPaths.some((path) =>
    request.nextUrl.pathname.startsWith(path),
  );

  if (!isProtected) {
    return;
  }

  if (!request.auth || hasSessionAuthError(request.auth)) {
    if (hasSessionAuthError(request.auth)) {
      const signOutUrl = new URL("/api/auth/signout", request.nextUrl.origin);
      signOutUrl.searchParams.set(
        "callbackUrl",
        new URL("/api/auth/signin", request.nextUrl.origin).toString(),
      );

      return NextResponse.redirect(signOutUrl);
    }

    const signInUrl = new URL("/api/auth/signin", request.nextUrl.origin);
    signInUrl.searchParams.set("callbackUrl", request.nextUrl.pathname);

    return NextResponse.redirect(signInUrl);
  }
});

export const config = {
  matcher: ["/portfolios/:path*", "/settings"],
};
