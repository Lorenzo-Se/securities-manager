"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";

import {
  acceptPortfolioShareAction,
  declinePortfolioShareAction,
} from "@/lib/actions/portfolio-share";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from "@/components/ui/card";
import type { PortfolioShareInvitation } from "@/lib/api/generated/models";

const shareTypeLabels = {
  viewer: "Betrachter",
  amount: "Feste Menge",
  percentage: "Prozentanteil",
} as const;

function formatInvitationDetails(invitation: PortfolioShareInvitation): string {
  if (invitation.share_type === "amount" && invitation.share_amount) {
    return `${invitation.share_amount} ${invitation.portfolio.crypto_symbol.toUpperCase()}`;
  }

  if (invitation.share_type === "percentage" && invitation.share_percentage) {
    return `${invitation.share_percentage} %`;
  }

  return "Voller Lesezugriff";
}

type PortfolioInvitationsBannerProps = {
  invitations: PortfolioShareInvitation[];
};

export function PortfolioInvitationsBanner({
  invitations,
}: PortfolioInvitationsBannerProps) {
  const router = useRouter();
  const [isPending, startTransition] = useTransition();
  const [activeId, setActiveId] = useState<number | null>(null);

  if (invitations.length === 0) {
    return null;
  }

  const handleAccept = (shareId: number) => {
    setActiveId(shareId);
    startTransition(async () => {
      await acceptPortfolioShareAction(shareId);
      setActiveId(null);
      router.refresh();
    });
  };

  const handleDecline = (shareId: number) => {
    setActiveId(shareId);
    startTransition(async () => {
      await declinePortfolioShareAction(shareId);
      setActiveId(null);
      router.refresh();
    });
  };

  return (
    <div className="mb-6 space-y-3">
      <h3 className="text-lg font-medium">Einladungen</h3>
      {invitations.map((invitation) => (
        <Card key={invitation.id} className="border-primary/30">
          <CardHeader className="pb-3">
            <div className="flex flex-wrap items-start justify-between gap-3">
              <div className="space-y-1">
                <CardTitle className="text-base">
                  {invitation.portfolio.name}
                </CardTitle>
                <CardDescription>
                  {invitation.invited_by.name} ({invitation.invited_by.email})
                  hat dich eingeladen
                </CardDescription>
              </div>
              <Badge variant="secondary">Ausstehend</Badge>
            </div>
          </CardHeader>
          <CardContent className="flex flex-wrap items-center justify-between gap-3">
            <p className="text-sm text-muted-foreground">
              {shareTypeLabels[invitation.share_type]} ·{" "}
              {formatInvitationDetails(invitation)}
            </p>
            <div className="flex gap-2">
              <Button
                variant="outline"
                size="sm"
                disabled={isPending && activeId === invitation.id}
                onClick={() => handleDecline(invitation.id)}
              >
                Ablehnen
              </Button>
              <Button
                size="sm"
                disabled={isPending && activeId === invitation.id}
                onClick={() => handleAccept(invitation.id)}
              >
                Annehmen
              </Button>
            </div>
          </CardContent>
        </Card>
      ))}
    </div>
  );
}
