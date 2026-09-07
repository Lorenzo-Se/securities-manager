"use client";

import { useState, useTransition } from "react";
import { useRouter } from "next/navigation";

import {
  createPortfolioShareAction,
  revokePortfolioShareAction,
} from "@/lib/actions/portfolio-share";
import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Checkbox } from "@/components/ui/checkbox";
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog";
import { Field, FieldGroup, FieldLabel } from "@/components/ui/field";
import { Input } from "@/components/ui/input";
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from "@/components/ui/select";
import type {
  CreatePortfolioShareBodyShareType,
  Portfolio,
  PortfolioShare,
  PortfolioShareStatus,
} from "@/lib/api/generated/models";
import { CreatePortfolioShareBodyShareType as ShareTypes } from "@/lib/api/generated/models/createPortfolioShareBodyShareType";

const statusLabels: Record<PortfolioShareStatus, string> = {
  pending: "Ausstehend",
  accepted: "Angenommen",
  declined: "Abgelehnt",
  revoked: "Widerrufen",
};

const shareTypeLabels: Record<CreatePortfolioShareBodyShareType, string> = {
  viewer: "Betrachter",
  amount: "Feste Menge",
  percentage: "Prozentanteil",
};

const shareTypeSelectLabels: Record<CreatePortfolioShareBodyShareType, string> = {
  viewer: "Betrachter (nur lesen)",
  amount: "Feste Menge gehört der Person",
  percentage: "Prozentanteil am Portfolio",
};

type PortfolioShareDialogProps = {
  portfolio: Portfolio;
  shares: PortfolioShare[];
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function PortfolioShareDialog({
  portfolio,
  shares,
  open,
  onOpenChange,
}: PortfolioShareDialogProps) {
  const router = useRouter();
  const [email, setEmail] = useState("");
  const [shareType, setShareType] = useState<CreatePortfolioShareBodyShareType>(
    ShareTypes.viewer,
  );
  const [shareAmount, setShareAmount] = useState("");
  const [sharePercentage, setSharePercentage] = useState("");
  const [showTotalBalance, setShowTotalBalance] = useState(false);
  const [error, setError] = useState<string | null>(null);
  const [isPending, startTransition] = useTransition();
  const [revokingId, setRevokingId] = useState<number | null>(null);

  const resetForm = () => {
    setEmail("");
    setShareType(ShareTypes.viewer);
    setShareAmount("");
    setSharePercentage("");
    setShowTotalBalance(false);
    setError(null);
  };

  const handleSubmit = () => {
    setError(null);

    startTransition(async () => {
      const result = await createPortfolioShareAction(portfolio.id, {
        email,
        share_type: shareType,
        share_amount:
          shareType === ShareTypes.amount ? shareAmount : undefined,
        share_percentage:
          shareType === ShareTypes.percentage ? sharePercentage : undefined,
        show_total_balance:
          shareType === ShareTypes.viewer ? false : showTotalBalance,
      });

      if (!result.ok) {
        setError(result.error);
        return;
      }

      resetForm();
      router.refresh();
    });
  };

  const handleRevoke = (shareId: number) => {
    setRevokingId(shareId);

    startTransition(async () => {
      const result = await revokePortfolioShareAction(portfolio.id, shareId);

      if (!result.ok) {
        setError(result.error);
      } else {
        router.refresh();
      }

      setRevokingId(null);
    });
  };

  const formatShareDetails = (share: PortfolioShare) => {
    if (share.share_type === "amount" && share.share_amount) {
      return `${share.share_amount} ${portfolio.crypto_symbol.toUpperCase()}`;
    }

    if (share.share_type === "percentage" && share.share_percentage) {
      return `${share.share_percentage} %`;
    }

    return "Voller Lesezugriff";
  };

  return (
    <Dialog
      open={open}
      onOpenChange={(nextOpen) => {
        if (!nextOpen) {
          resetForm();
        }

        onOpenChange(nextOpen);
      }}
    >
      <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
        <DialogHeader>
          <DialogTitle>Portfolio teilen</DialogTitle>
          <DialogDescription>
            Lade andere per E-Mail ein. Sie müssen die Einladung annehmen, um
            Zugriff zu erhalten.
          </DialogDescription>
        </DialogHeader>

        <FieldGroup>
          <Field>
            <FieldLabel htmlFor="share-email">E-Mail-Adresse</FieldLabel>
            <Input
              id="share-email"
              type="email"
              value={email}
              onChange={(event) => setEmail(event.target.value)}
              placeholder="person@example.com"
            />
          </Field>

          <Field>
            <FieldLabel>Freigabeart</FieldLabel>
            <Select
              value={shareType}
              onValueChange={(value) => {
                if (value) {
                  setShareType(value as CreatePortfolioShareBodyShareType);
                }
              }}
            >
              <SelectTrigger className="w-full">
                <SelectValue placeholder="Freigabeart wählen">
                  {shareTypeSelectLabels[shareType]}
                </SelectValue>
              </SelectTrigger>
              <SelectContent>
                <SelectItem value={ShareTypes.viewer}>
                  {shareTypeSelectLabels[ShareTypes.viewer]}
                </SelectItem>
                <SelectItem value={ShareTypes.amount}>
                  {shareTypeSelectLabels[ShareTypes.amount]}
                </SelectItem>
                <SelectItem value={ShareTypes.percentage}>
                  {shareTypeSelectLabels[ShareTypes.percentage]}
                </SelectItem>
              </SelectContent>
            </Select>
          </Field>

          {shareType === ShareTypes.amount ? (
            <Field>
              <FieldLabel htmlFor="share-amount">
                Menge ({portfolio.crypto_symbol.toUpperCase()})
              </FieldLabel>
              <Input
                id="share-amount"
                type="text"
                inputMode="decimal"
                value={shareAmount}
                onChange={(event) => setShareAmount(event.target.value)}
                placeholder="0.5"
              />
            </Field>
          ) : null}

          {shareType === ShareTypes.percentage ? (
            <Field>
              <FieldLabel htmlFor="share-percentage">Prozentanteil</FieldLabel>
              <Input
                id="share-percentage"
                type="text"
                inputMode="decimal"
                value={sharePercentage}
                onChange={(event) => setSharePercentage(event.target.value)}
                placeholder="25"
              />
            </Field>
          ) : null}

          {shareType !== ShareTypes.viewer ? (
            <Field orientation="horizontal">
              <Checkbox
                id="show-total-balance"
                checked={showTotalBalance}
                onCheckedChange={(checked) =>
                  setShowTotalBalance(checked === true)
                }
              />
              <FieldLabel htmlFor="show-total-balance" className="font-normal">
                Gesamtbetrag ebenfalls anzeigen
              </FieldLabel>
            </Field>
          ) : null}

          {error ? <p className="text-sm text-destructive">{error}</p> : null}
        </FieldGroup>

        {shares.length > 0 ? (
          <div className="space-y-3">
            <h4 className="text-sm font-medium">Bestehende Freigaben</h4>
            <div className="space-y-2">
              {shares.map((share) => (
                <div
                  key={share.id}
                  className="flex items-start justify-between gap-3 rounded-lg border p-3"
                >
                  <div className="min-w-0 space-y-1">
                    <p className="truncate text-sm font-medium">
                      {share.invited_email}
                    </p>
                    <p className="text-xs text-muted-foreground">
                      {shareTypeLabels[share.share_type]} ·{" "}
                      {formatShareDetails(share)}
                    </p>
                    <Badge variant="secondary">
                      {statusLabels[share.status]}
                    </Badge>
                  </div>
                  {share.status === "pending" || share.status === "accepted" ? (
                    <Button
                      variant="outline"
                      size="sm"
                      disabled={isPending && revokingId === share.id}
                      onClick={() => handleRevoke(share.id)}
                    >
                      Widerrufen
                    </Button>
                  ) : null}
                </div>
              ))}
            </div>
          </div>
        ) : null}

        <DialogFooter>
          <Button
            variant="outline"
            onClick={() => onOpenChange(false)}
            disabled={isPending}
          >
            Schließen
          </Button>
          <Button onClick={handleSubmit} disabled={isPending || !email}>
            Einladung senden
          </Button>
        </DialogFooter>
      </DialogContent>
    </Dialog>
  );
}
