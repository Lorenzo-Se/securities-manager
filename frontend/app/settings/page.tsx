import { SettingsPageContent } from "@/components/settings-page-content";
import type { GetAuthenticatedUser200 } from "@/lib/api/generated/models";
import { getAuthenticatedUser } from "@/lib/api/generated/user/user";
import { unwrap } from "@/lib/api/unwrap";

export default async function SettingsPage() {
  const user = await getAuthenticatedUser().then((response) =>
    unwrap<GetAuthenticatedUser200>(response),
  );

  return <SettingsPageContent user={user} />;
}
