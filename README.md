# 🚀 Mixpost Online: Vercel + Supabase + AI Post Automation

A complete, **100% free-tier** architecture to deploy [Mixpost](https://github.com/inovector/mixpost) to the cloud and automate multi-platform social media publishing with AI (Gemini, OpenAI, Claude).

---

## 🏗️ Architecture Overview

```
 ┌──────────────────────┐        ┌─────────────────────────┐
 │   Vercel Serverless  │◄───────┤ External Cron Scheduler │
 │  (Mixpost Dashboard  │        │ (cron-job.org or GitHub │
 │    & REST APIs)      │        │      Actions 5-min)     │
 └──────────┬───────────┘        └─────────────────────────┘
            │
            ├────────────────────────┐
            ▼                        ▼
 ┌──────────────────────┐  ┌───────────────────────┐
 │  Supabase PostgreSQL │  │  Supabase S3 Storage  │
 │ (Posts, Accounts, DB)│  │ (Media, Images, Video)│
 └──────────────────────┘  └───────────────────────┘
            ▲
            │
 ┌─────────────────────────────────────────────────────────┐
 │          AI Post Automation Engine                      │
 │ (Multi-Provider: Gemini Free, OpenAI, Anthropic Claude) │
 └─────────────────────────────────────────────────────────┘
```

- **Web & API Host:** Vercel (Hobby Free Plan) with `vercel-php` runtime.
- **Database & Queue Storage:** Supabase Free PostgreSQL (500MB DB).
- **Media Asset Storage:** Supabase S3-compatible Storage (1GB Free).
- **Background Scheduler:** Free external ping via `cron-job.org` or GitHub Actions to `/api/cron?token=YOUR_CRON_SECRET`.
- **Content Automation:** Multi-AI generator (Google Gemini, OpenAI GPT-4o-mini, Anthropic Claude) + Pollinations/Unsplash image generation.

---

## 📋 Step-by-Step Deployment Guide

### 1️⃣ Step 1: Create Free Supabase Project

1. Go to [supabase.com](https://supabase.com) and create a free account & project.
2. Under **Project Settings** ➔ **Database**:
   - Copy the **Host**, **Port (5432)**, **Database (postgres)**, **User (postgres)**, and your database password.
3. Under **Storage**:
   - Create a new public bucket named `mixpost-media`.
   - Go to **Project Settings** ➔ **Storage** ➔ **S3 Access Keys** and create an S3 Access Key ID & Secret.

---

### 2️⃣ Step 2: Push Repository to GitHub

Initialize git and push this folder to your GitHub repository:

```bash
git init
git add .
git commit -m "feat: mixpost serverless vercel + supabase setup"
git branch -M main
git remote add origin https://github.com/YOUR_USERNAME/mixpost-online.git
git push -u origin main
```

---

### 3️⃣ Step 3: Deploy to Vercel

1. Go to [vercel.com](https://vercel.com) and click **"Add New Project"** ➔ **Import Git Repository**.
2. Select your `mixpost-online` repository.
3. In the **Environment Variables** section, add the following (copy from `.env.example`):

| Variable | Description / Example Value |
| :--- | :--- |
| `APP_NAME` | `Mixpost` |
| `APP_ENV` | `production` |
| `APP_KEY` | `base64:YOUR_APP_KEY` (Generate via `php artisan key:generate` or a 32-char string) |
| `APP_DEBUG` | `false` |
| `APP_URL` | `https://your-mixpost-project.vercel.app` |
| `DB_CONNECTION` | `pgsql` |
| `DB_HOST` | `aws-0-us-east-1.pooler.supabase.com` (Your Supabase Host) |
| `DB_PORT` | `5432` |
| `DB_DATABASE` | `postgres` |
| `DB_USERNAME` | `postgres` |
| `DB_PASSWORD` | `your_supabase_db_password` |
| `DB_SSLMODE` | `require` |
| `CACHE_STORE` | `database` |
| `SESSION_DRIVER` | `database` |
| `QUEUE_CONNECTION` | `database` |
| `FILESYSTEM_DISK` | `s3` |
| `AWS_ACCESS_KEY_ID` | Your Supabase S3 Access Key ID |
| `AWS_SECRET_ACCESS_KEY`| Your Supabase S3 Secret Access Key |
| `AWS_BUCKET` | `mixpost-media` |
| `AWS_ENDPOINT` | `https://YOUR_PROJECT_REF.supabase.co/storage/v1/s3` |
| `CRON_SECRET` | A secure random string (e.g., `mixpost_cron_secure_token_987654`) |
| `AI_PROVIDER` | `gemini` (or `openai` / `claude`) |
| `GEMINI_API_KEY` | Your Google AI Studio API key (free at aistudio.google.com) |

4. Click **Deploy**. Vercel will build and launch your application.

---

### 4️⃣ Step 4: Configure Social Media Developer Apps

In your Mixpost Dashboard (`https://your-project.vercel.app/mixpost`), configure the platforms you selected:

- **LinkedIn**: Create an app at [LinkedIn Developer Portal](https://www.linkedin.com/developers). Set redirect URL to `https://your-project.vercel.app/mixpost/callback/linkedin`.
- **X (Twitter)**: Create a project at [Twitter Developer Portal](https://developer.twitter.com). Enable OAuth 2.0 with redirect URL `https://your-project.vercel.app/mixpost/callback/twitter`.
- **Facebook & Instagram**: Create an app at [Meta Developers](https://developers.facebook.com). Set redirect URL `https://your-project.vercel.app/mixpost/callback/facebook`.
- **Pinterest / YouTube / Mastodon / Threads**: Follow the callback URLs specified in `.env.example`.

---

### 5️⃣ Step 5: Setup Free External Cron (24/7 Posting Trigger)

Since serverless functions don't run continuous background daemons, choose one of these **100% free** schedulers:

#### Option A: cron-job.org (Recommended - 1 minute interval)
1. Register for free at [cron-job.org](https://cron-job.org).
2. Create a new Cronjob:
   - **URL:** `https://your-project.vercel.app/api/cron?token=YOUR_CRON_SECRET`
   - **Schedule:** Every 1 minute.
   - **Request Method:** `GET`

#### Option B: GitHub Actions (Already Included)
1. In your GitHub repository, go to **Settings ➔ Secrets and variables ➔ Actions**.
2. Add secrets:
   - `APP_URL`: `https://your-project.vercel.app`
   - `CRON_SECRET`: `YOUR_CRON_SECRET`
3. The `.github/workflows/scheduler.yml` workflow will automatically run every 5 minutes to process scheduled posts.

---

## 🤖 AI Content Automation

### 1. Generating Posts via Webhook / REST API
You can trigger automatic post generation from Zapier, Make, n8n, or any curl script:

```bash
curl -X POST "https://your-project.vercel.app/api/automation/generate-post" \
  -H "Authorization: Bearer YOUR_CRON_SECRET" \
  -H "Content-Type: application/json" \
  -d '{
    "topic": "The future of autonomous AI agents in 2026",
    "schedule_hours": 3
  }'
```

### 2. Standalone Local / CLI Auto-Poster
You can also run the built-in Node.js generator locally or inside any automation script:

```bash
# Install dependencies
npm install

# Run the AI generator
node scripts/ai_auto_poster.js "Top 5 developer productivity tips" 2
```

---

## 📂 Project Structure

```
├── .github/workflows/
│   └── scheduler.yml            # GitHub Actions 24/7 cron runner
├── api/
│   ├── index.php                # Vercel serverless HTTP entrypoint
│   └── cron.php                 # Secured scheduler & queue execution endpoint
├── app/
│   ├── Console/Commands/
│   │   └── GenerateAiPosts.php  # Artisan command for AI post generation
│   └── Services/
│       └── AiContentService.php # Multi-AI Provider (Gemini, OpenAI, Claude, Pollinations)
├── config/
│   ├── database.php             # Supabase PostgreSQL configuration
│   ├── filesystems.php          # Supabase S3 media storage
│   ├── mixpost.php              # Mixpost package settings
│   └── services.php             # AI and Social Media API configs
├── routes/
│   ├── api.php                  # Automation webhooks & healthcheck
│   ├── console.php              # Scheduled task definitions
│   └── web.php                  # Web redirects
├── scripts/
│   └── ai_auto_poster.js        # Standalone Node.js AI content generator & scheduler
├── .env.example                 # Complete environment configuration template
├── composer.json                # PHP & Laravel package dependencies
├── package.json                 # Node dependencies for automation scripts
└── vercel.json                  # Vercel serverless deployment config
```
