# You Pinterest

> **Your photos stay in your Google Drive. You Pinterest turns them into a beautiful Pinterest-like experience for your client.**

You Pinterest is an open-source photo delivery application for photographers. Select a Google Drive folder, publish a gallery, share a URL. Your client sees a Pinterest-style masonry feed. No re-uploading. No storage costs. No complexity.

---

## What it is

- A self-hosted, open-source alternative to client gallery delivery apps
- Backed entirely by your Google Drive — photos never leave your Drive
- Pinterest-style masonry layout with fullscreen lightbox viewer
- Protected galleries with 6-digit access codes
- Simple Filament-based admin panel for photographers

## What it is not

- Not a portfolio builder
- Not a DAM or photo storage platform
- Not a website builder
- Not SaaS (self-hosted only)

---

## Architecture

```
Laravel 13 monolith
├── Domain/
│   ├── Google/      — OAuth, Drive abstraction
│   ├── Gallery/     — Gallery model, sync, access codes
│   ├── Photo/       — Photo metadata
│   └── Client/      — Client management
├── Filament/        — Admin panel (Resources, Pages)
└── Http/Controllers/Gallery/  — Public gallery + download
```

The application stores **metadata only**. Google Drive is the source of truth for actual image files.

---

## Requirements

- PHP 8.3+
- PostgreSQL 15+
- Redis 7+
- Node.js 20+
- A Google Cloud project with OAuth 2.0 credentials

---

## Quick start (Docker)

```bash
# 1. Clone
git clone https://github.com/yourname/your-pinterest.git
cd your-pinterest

# 2. Configure environment
cp .env.example .env
# Edit .env — set GOOGLE_CLIENT_ID, GOOGLE_CLIENT_SECRET, and DB credentials

# 3. Start containers
docker compose up -d

# 4. Install dependencies & bootstrap
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app npm ci
docker compose exec app npm run build

# 5. Create your photographer account
docker compose exec app php artisan tinker
# >>> \App\Models\User::create(['name'=>'...','email'=>'...','password'=>bcrypt('...')])

# 6. Open http://localhost:8000/admin
```

---

## Local development (Herd / Valet)

```bash
git clone https://github.com/yourname/your-pinterest.git
cd your-pinterest
cp .env.example .env

# Edit .env — set DB_CONNECTION=pgsql and Google credentials

composer install
php artisan key:generate
php artisan migrate

npm install
npm run dev

# Visit http://your-pinterest.test/admin
```

---

## Google Cloud setup

### 1. Create a project

Go to [Google Cloud Console](https://console.cloud.google.com/) and create a new project.

### 2. Enable the Drive API

- Navigate to **APIs & Services → Library**
- Search for **Google Drive API** and enable it

### 3. Create OAuth credentials

- Navigate to **APIs & Services → Credentials**
- Click **Create Credentials → OAuth client ID**
- Application type: **Web application**
- Add an authorised redirect URI:
  ```
  http://localhost:8000/auth/google/callback
  ```
  (Replace with your production URL when deploying)

### 4. Configure the OAuth consent screen

- Set **User type** to **External** (or Internal if using Google Workspace)
- Add the scope: `https://www.googleapis.com/auth/drive.readonly`
- Add yourself as a test user during development

### 5. Copy credentials to .env

```env
GOOGLE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GOOGLE_CLIENT_SECRET=your-client-secret
GOOGLE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

---

## Permissions

You Pinterest requests only:

```
https://www.googleapis.com/auth/drive.readonly
```

This gives read-only access to list and download files. The application cannot create, edit, or delete anything in your Drive.

---

## Usage

1. Register / log in at `/admin`
2. Navigate to **Google Drive** and connect your account
3. Go to **Galleries → New Gallery**
4. Set a name, paste or type the Google Drive folder ID, choose access type
5. Save — photos are synced automatically in the background
6. Publish the gallery and copy the URL (e.g. `/g/john-and-maria`)
7. Share with your client

---

## Running queues

Photo synchronisation runs in the background via Laravel queues.

**Docker:** the `queue` container handles this automatically.

**Local:**
```bash
php artisan queue:work
```

---

## Running tests

```bash
php artisan test
```

---

## Contributing

See [CONTRIBUTING.md](CONTRIBUTING.md).

---

## License

MIT — see [LICENSE](LICENSE).
