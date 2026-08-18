"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { de } from "date-fns/locale";
import { CalendarIcon } from "lucide-react";

import { Button } from "@/components/ui/button";
import { Calendar } from "@/components/ui/calendar";
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
  Popover,
  PopoverContent,
  PopoverTrigger,
} from "@/components/ui/popover";
import {
  getGetPortfolioQueryKey,
  getListPortfolioMovementsQueryKey,
  getListPortfoliosQueryKey,
  useCreatePortfolioMovement,
} from "@/lib/api/generated/portfolio/portfolio";
import type { CreatePortfolioMovementBodyType } from "@/lib/api/generated/models";
import { cn } from "@/lib/utils";

type PortfolioMovementDialogProps = {
  portfolioId: number;
  open: boolean;
  onOpenChange: (open: boolean) => void;
};

export function PortfolioMovementDialog({
  portfolioId,
  open,
  onOpenChange,
}: PortfolioMovementDialogProps) {
  const queryClient = useQueryClient();
  const [type, setType] = useState<CreatePortfolioMovementBodyType>("deposit");
  const [amount, setAmount] = useState("");
  const [date, setDate] = useState<Date | undefined>(new Date());
  const [calendarOpen, setCalendarOpen] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const createMovement = useCreatePortfolioMovement({
    mutation: {
      onSuccess: async () => {
        await Promise.all([
          queryClient.invalidateQueries({
            queryKey: getListPortfolioMovementsQueryKey(portfolioId),
          }),
          queryClient.invalidateQueries({
            queryKey: getGetPortfolioQueryKey(portfolioId),
          }),
          queryClient.invalidateQueries({
            queryKey: getListPortfoliosQueryKey(),
          }),
        ]);
        resetForm();
        onOpenChange(false);
      },
      onError: (mutationError) => {
        const info = mutationError.info as
          | { message?: string; errors?: Record<string, string[]> }
          | undefined;

        if (info?.errors?.amount?.[0]) {
          setError(info.errors.amount[0]);
          return;
        }

        setError("Buchung konnte nicht gespeichert werden.");
      },
    },
  });

  const resetForm = () => {
    setType("deposit");
    setAmount("");
    setDate(new Date());
    setError(null);
  };

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (!date) {
      setError("Bitte wähle ein Datum aus.");
      return;
    }

    const parsedAmount = Number(amount);
    if (!amount || Number.isNaN(parsedAmount) || parsedAmount <= 0) {
      setError("Bitte gib eine gültige Menge ein.");
      return;
    }

    createMovement.mutate({
      portfolio: portfolioId,
      data: {
        type,
        amount: amount.trim(),
        date: format(date, "yyyy-MM-dd"),
      },
    });
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
      <DialogContent className="sm:max-w-md">
        <DialogHeader>
          <DialogTitle>Buchung hinzufügen</DialogTitle>
          <DialogDescription>
            Erfasse eine Ein- oder Auszahlung mit Datum und Menge.
          </DialogDescription>
        </DialogHeader>

        <form onSubmit={handleSubmit}>
          <FieldGroup className="gap-4">
            <Field>
              <FieldLabel>Typ</FieldLabel>
              <div className="grid grid-cols-2 gap-2">
                <Button
                  type="button"
                  variant={type === "deposit" ? "default" : "outline"}
                  onClick={() => setType("deposit")}
                >
                  Einzahlung
                </Button>
                <Button
                  type="button"
                  variant={type === "withdrawal" ? "default" : "outline"}
                  onClick={() => setType("withdrawal")}
                >
                  Auszahlung
                </Button>
              </div>
            </Field>

            <Field>
              <FieldLabel>Datum</FieldLabel>
              <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
                <PopoverTrigger
                  render={
                    <Button
                      type="button"
                      variant="outline"
                      className={cn(
                        "w-full justify-start text-left font-normal",
                        !date && "text-muted-foreground",
                      )}
                    />
                  }
                >
                  <CalendarIcon className="size-4" />
                  {date
                    ? format(date, "PPP", { locale: de })
                    : "Datum wählen"}
                </PopoverTrigger>
                <PopoverContent className="w-auto p-0" align="start">
                  <Calendar
                    mode="single"
                    selected={date}
                    onSelect={(selectedDate) => {
                      setDate(selectedDate);
                      setCalendarOpen(false);
                    }}
                    disabled={(day) => day > new Date()}
                    defaultMonth={date}
                  />
                </PopoverContent>
              </Popover>
            </Field>

            <Field>
              <FieldLabel htmlFor="movement-amount">Menge</FieldLabel>
              <Input
                id="movement-amount"
                type="text"
                inputMode="decimal"
                value={amount}
                onChange={(event) => setAmount(event.target.value)}
                placeholder="z. B. 0.5"
                required
              />
            </Field>

            {error ? (
              <p className="text-sm text-destructive">{error}</p>
            ) : null}
          </FieldGroup>

          <DialogFooter className="mt-6">
            <Button
              type="button"
              variant="outline"
              onClick={() => onOpenChange(false)}
            >
              Abbrechen
            </Button>
            <Button type="submit" disabled={createMovement.isPending}>
              {createMovement.isPending ? "Speichere..." : "Speichern"}
            </Button>
          </DialogFooter>
        </form>
      </DialogContent>
    </Dialog>
  );
}
