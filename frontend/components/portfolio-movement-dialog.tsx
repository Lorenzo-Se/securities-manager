"use client";

import { useState } from "react";
import { useQueryClient } from "@tanstack/react-query";
import { format } from "date-fns";
import { de } from "date-fns/locale";
import { ChevronDownIcon } from "lucide-react";

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

function toTimeInputValue(date: Date) {
  return format(date, "HH:mm");
}

function combineDateAndTime(date: Date, time: string): Date | null {
  const [hours, minutes] = time.split(":").map(Number);

  if (Number.isNaN(hours) || Number.isNaN(minutes)) {
    return null;
  }

  const combined = new Date(date);
  combined.setHours(hours, minutes, 0, 0);

  return combined;
}

function getDefaultDateTime() {
  const now = new Date();

  return {
    date: now,
    time: toTimeInputValue(now),
  };
}

export function PortfolioMovementDialog({
  portfolioId,
  open,
  onOpenChange,
}: PortfolioMovementDialogProps) {
  const queryClient = useQueryClient();
  const [type, setType] = useState<CreatePortfolioMovementBodyType>("deposit");
  const [amount, setAmount] = useState("");
  const [date, setDate] = useState<Date | undefined>(() => getDefaultDateTime().date);
  const [time, setTime] = useState(() => getDefaultDateTime().time);
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

        if (info?.errors?.occurred_at?.[0]) {
          setError(info.errors.occurred_at[0]);
          return;
        }

        setError("Buchung konnte nicht gespeichert werden.");
      },
    },
  });

  const resetForm = () => {
    const defaults = getDefaultDateTime();
    setType("deposit");
    setAmount("");
    setDate(defaults.date);
    setTime(defaults.time);
    setCalendarOpen(false);
    setError(null);
  };

  const handleSubmit = (event: React.FormEvent<HTMLFormElement>) => {
    event.preventDefault();
    setError(null);

    if (!date) {
      setError("Bitte wähle ein Datum aus.");
      return;
    }

    if (!time) {
      setError("Bitte wähle eine Uhrzeit aus.");
      return;
    }

    const parsedAmount = Number(amount);
    if (!amount || Number.isNaN(parsedAmount) || parsedAmount <= 0) {
      setError("Bitte gib eine gültige Menge ein.");
      return;
    }

    const occurredAtDate = combineDateAndTime(date, time);
    if (!occurredAtDate || Number.isNaN(occurredAtDate.getTime())) {
      setError("Bitte wähle einen gültigen Zeitpunkt aus.");
      return;
    }

    if (occurredAtDate.getTime() > Date.now()) {
      setError("Der Zeitpunkt darf nicht in der Zukunft liegen.");
      return;
    }

    createMovement.mutate({
      portfolio: portfolioId,
      data: {
        type,
        amount: amount.trim(),
        occurred_at: occurredAtDate.toISOString(),
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
            Erfasse eine Ein- oder Auszahlung mit Zeitpunkt und Menge.
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

            <FieldGroup className="flex-row gap-4">
              <Field className="flex-1">
                <FieldLabel htmlFor="movement-date">Datum</FieldLabel>
                <Popover open={calendarOpen} onOpenChange={setCalendarOpen}>
                  <PopoverTrigger
                    render={
                      <Button
                        type="button"
                        variant="outline"
                        id="movement-date"
                        className={cn(
                          "w-full justify-between font-normal",
                          !date && "text-muted-foreground",
                        )}
                      />
                    }
                  >
                    {date
                      ? format(date, "PPP", { locale: de })
                      : "Datum wählen"}
                    <ChevronDownIcon data-icon="inline-end" />
                  </PopoverTrigger>
                  <PopoverContent
                    className="w-auto overflow-hidden p-0"
                    align="start"
                  >
                    <Calendar
                      mode="single"
                      selected={date}
                      captionLayout="dropdown"
                      defaultMonth={date}
                      locale={de}
                      onSelect={(selectedDate) => {
                        setDate(selectedDate);
                        setCalendarOpen(false);
                      }}
                      disabled={(day) => day > new Date()}
                    />
                  </PopoverContent>
                </Popover>
              </Field>
              <Field className="w-32">
                <FieldLabel htmlFor="movement-time">Uhrzeit</FieldLabel>
                <Input
                  type="time"
                  id="movement-time"
                  value={time}
                  onChange={(event) => setTime(event.target.value)}
                  className="appearance-none bg-background [&::-webkit-calendar-picker-indicator]:hidden [&::-webkit-calendar-picker-indicator]:appearance-none"
                  required
                />
              </Field>
            </FieldGroup>

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
