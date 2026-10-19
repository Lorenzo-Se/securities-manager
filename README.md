# Securities Manager

Monorepo für die Full-Stack-Anwendung zur Verwaltung von Wertpapier-Portfolios: Laravel-API, Next.js-Frontend und Keycloak-Authentifizierung.

## Stack

| Bereich | Technologie |
|---------|-------------|
| Frontend | Next.js 16, React, Auth.js, shadcn/ui, Tailwind CSS |
| Backend | Laravel 13, PostgreSQL, L5-Swagger |
| Auth | Keycloak 26 (OIDC / JWT) |
| Infrastruktur | Docker Compose (PostgreSQL + Keycloak) |

## Voraussetzungen

- Docker & Docker Compose
- PHP 8.4+ und Composer
- Node.js 20+ und npm

## Schnellstart

### 1. Infrastruktur starten

```bash
docker compose up -d
```

| Dienst | URL | Zugangsdaten |
|--------|-----|--------------|
| Keycloak Admin | http://localhost:8080 | `admin` / `admin` |
| PostgreSQL | `localhost:5432` | User `keycloak`, DB `keycloak` |

**Test-User:** `demo@example.com` / `demo`  
**Realm:** `securities-manager` · **Client:** `frontend` (Confidential, PKCE)

Der Realm wird beim ersten Start aus [`keycloak/realm-securities-manager.json`](keycloak/realm-securities-manager.json) importiert.

Falls Keycloak bereits mit einem älteren Realm läuft (z. B. nach Änderung am Client-Typ), Volume zurücksetzen und neu importieren:

```bash
docker compose down
docker volume rm securities-manager-old_keycloak_postgres_data
docker compose up -d
```

Danach erneut einloggen — bestehende Sessions sind ungültig.

### 2. Backend

```bash
cd backend
cp .env.example .env   # falls noch nicht vorhanden
composer install
php artisan key:generate
php artisan migrate
php artisan serve
```

Wichtige Variablen in `backend/.env`:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=securities_manager
DB_USERNAME=laravel
DB_PASSWORD=laravel

KEYCLOAK_BASE_URL=http://localhost:8080
KEYCLOAK_REALM=securities-manager
KEYCLOAK_CLIENT_ID=frontend
FRONTEND_URL=http://localhost:3000
```

- API: http://localhost:8000/api
- Swagger: http://localhost:8000/api/documentation

Falls die Laravel-Datenbank noch fehlt (ältere Docker-Volumes):

```bash
docker compose exec postgres psql -U keycloak -d keycloak -c \
  "CREATE USER laravel WITH PASSWORD 'laravel'; CREATE DATABASE securities_manager OWNER laravel;"
```

### 3. Frontend

```bash
cd frontend
cp .env.example .env.local
npm install
npm run dev
```

Wichtige Variablen in `frontend/.env.local`:

```env
API_URL=http://localhost:8000/api
AUTH_URL=http://localhost:3000
AUTH_SECRET=<zufälliger-32-zeichen-string>
AUTH_KEYCLOAK_ID=frontend
AUTH_KEYCLOAK_SECRET=frontend-dev-secret
AUTH_KEYCLOAK_ISSUER=http://localhost:8080/realms/securities-manager
AUTH_TRUST_HOST=true
```

`API_URL` ist server-only und wird für SSR-Datenabrufe sowie Server Actions genutzt. Der Browser kommuniziert nicht direkt mit dem Laravel-Backend.

`AUTH_KEYCLOAK_SECRET` muss dem Client-Secret in Keycloak entsprechen (lokal: `frontend-dev-secret` aus dem Realm-Import). Nur serverseitig setzen, nie als `NEXT_PUBLIC_*`.

`AUTH_SECRET` erzeugen: `openssl rand -base64 32`

Frontend: http://localhost:3000

## Produktion

Läuft auf `https://securities.seminara.ch` (Keycloak: `https://securities-auth.seminara.ch`), deployt von `infrastructure/pve-iac` (Stack `lse-securities-manager`, Doku `docs/lse-securities-manager.md`). Der Stack ist [`compose.prod.yml`](compose.prod.yml): Frontend (Port 80), Laravel-API mit Queue-Worker und Scheduler (nur intern), Keycloak (Port 8080). PostgreSQL ist extern; alle Werte kommen aus der `.env` neben der Compose-Datei, die Ansible schreibt.

```bash
docker compose -f compose.prod.yml up -d --build
```

Keycloak importiert beim ersten Start [`keycloak/realm-securities-manager.prod.json`](keycloak/realm-securities-manager.prod.json) (ohne Benutzer, Client-Secret und Redirect-URIs aus der Umgebung). Danach Änderungen nur noch in der Admin-Konsole. Neue Releases: nach Push auf `main` den **configure**-Job für `lse-securities-manager` in pve-iac starten.

## Projektstruktur

```
├── docker-compose.yml      # Lokal: PostgreSQL + Keycloak
├── compose.prod.yml        # Produktion (pve-iac)
├── docker/postgres/init/   # Init-Skript für securities_manager DB
├── keycloak/               # Realm-Import
├── backend/                # Laravel API (Portfolios, Krypto-Preise, Auth)
└── frontend/               # Next.js App (Portfolios, Einstellungen)
```

## Authentifizierung

1. **Anmelden** auf der Startseite → Redirect zu Keycloak
2. Nach Login Session im Frontend
3. Frontend sendet `Authorization: Bearer <token>` an die API
4. Laravel validiert das JWT gegen Keycloaks JWKS und legt den User bei Bedarf an

Callback-URL für Auth.js:

`http://localhost:3000/api/auth/callback/keycloak`

## API (Auszug)

| Endpunkt | Auth | Beschreibung |
|----------|------|--------------|
| `GET /api/health` | Nein | API-Status |
| `GET /api/user` | Ja | Aktueller Benutzer |
| `GET /api/portfolios` | Ja | Portfolios des Users |
| `GET /api/crypto/prices` | Ja | Krypto-Referenzpreise |

Vollständige Dokumentation: Swagger unter `/api/documentation`.

## Tests

```bash
# Backend
cd backend && php artisan test

# Frontend Build
cd frontend && npm run build
```

## On-Chain-Sync

Portfolios importieren Transaktionen automatisch über chain-spezifische, kostenlose APIs:

| Coin | Chain | Wallet-Typ | Provider | API-Key |
|------|-------|------------|----------|---------|
| BTC | Bitcoin | xpub / zpub / ypub / Adresse | blockchain.info (Legacy-xpub) / mempool.space (zpub/ypub + Adresse) | — (keyless) |
| ETH | Ethereum | Adresse (0x) | Etherscan | `ETHERSCAN_API_KEY` |
| USDT / USDC | Ethereum / BSC / Tron / Solana | Adresse | Etherscan / BscScan / TronGrid / Helius | je nach Chain (siehe unten) |
| BNB | BNB Chain | Adresse (0x) | BscScan | `BSCSCAN_API_KEY` |
| XRP | XRP Ledger | Adresse (r…) | XRPL Data API | — (keyless) |
| SOL | Solana | Adresse | Helius | `HELIUS_API_KEY` |
| TRX | Tron | Adresse (T…) | TronGrid | `TRONGRID_API_KEY` (optional) |
| ADA | Cardano | Adresse | Blockfrost | `BLOCKFROST_API_KEY` |
| DOGE / LTC | Dogecoin / Litecoin | Adresse | SoChain | — (keyless) |
| ZEC | Zcash | Adresse | SoChain | — (keyless) |
| HYPE | Hyperliquid | Adresse (0x) | Hyperliquid Info API | — (keyless) |

**USDT/USDC Multi-Chain:** Die Chain wird automatisch aus dem Adressformat erkannt (0x → Ethereum vor BSC, T… → Tron, Base58 → Solana).

```env
# backend/.env
ETHERSCAN_API_KEY=
BSCSCAN_API_KEY=
TRONGRID_API_KEY=
HELIUS_API_KEY=
BLOCKFROST_API_KEY=
```

Kostenlose API-Keys:
- [Etherscan](https://etherscan.io/apis)
- [BscScan](https://bscscan.com/apis)
- [TronGrid](https://www.trongrid.io/)
- [Helius](https://www.helius.dev/)
- [Blockfrost](https://blockfrost.io/)

Für asynchrone Synchronisation muss der Queue-Worker laufen:

```bash
cd backend && php artisan queue:work
```

Geplanter Sync alle 30 Minuten via `php artisan schedule:work` bzw. Cron.

## Fehlerbehebung

### `Invalid parameter: redirect_uri`

`AUTH_URL=http://localhost:3000` in `frontend/.env.local` setzen und in Keycloak unter **Clients → frontend → Valid redirect URIs** eintragen:

`http://localhost:3000/api/auth/callback/keycloak`

Realm neu importieren bei veralteter Konfiguration:

```bash
docker compose down -v && docker compose up -d
```

### API gibt `Unauthenticated` trotz Login

Keycloak 26 nutzt standardmäßig **Lightweight Access Tokens** ohne `sub`-Claim. Unter **Clients → frontend → Advanced** → **Always use lightweight access token** auf **OFF** setzen, oder Realm neu importieren.

### Docker startet nicht

Docker Desktop muss laufen, bevor `docker compose up -d` ausgeführt wird.

## Repository-Historie

Dieses Monorepo vereint die bisherigen separaten Repositories:

- `backend/` — Laravel API
- `frontend/` — Next.js App

Die Commit-Historie beider Projekte ist unter den jeweiligen Pfaden erhalten (`git log -- backend/`, `git log -- frontend/`).
