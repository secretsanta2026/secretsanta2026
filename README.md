# 🎅 Secret Santa Gift Exchange Application

A secure and simple web application for organizing Secret Santa gift exchanges in your company. Each participant receives a unique email link to reveal their Secret Santa assignment—fair, secure, and cheat-proof!

## ✨ Features

- **Fair Drawing Algorithm**: Ensures no one gets themselves as their Secret Santa
- **Unique Email Links**: Each participant gets a personalized, secure link
- **One-Time Reveal**: Each person can only reveal their assignment (tracked to prevent cheating)
- **Beautiful UI**: Clean, festive design that's easy to use
- **Admin Dashboard**: Track who has revealed their Secret Santa
- **Email Notifications**: Automatic email sending with attractive HTML templates
- **Secure Tokens**: Cryptographically secure tokens prevent tampering

## 🚀 Quick Start

### Prerequisites

- Node.js (v14 or higher)
- An email account for sending emails (Gmail recommended)

### Installation (Next.js)

1. **Install dependencies:**
   ```powershell
   npm install
   ```

2. **Configure email settings:**
   
   Copy `.env.example` to `.env`:
   ```powershell
   Copy-Item .env.example .env
   ```

   Edit `.env` and add your email credentials:
   ```env
   EMAIL_HOST=smtp.gmail.com
   EMAIL_PORT=587
   EMAIL_USER=your-email@gmail.com
   EMAIL_PASS=your-app-password
   
   PORT=3000
   APP_URL=http://localhost:3000
   ```

   **For Gmail users:**
   - You need to use an "App Password" (not your regular password)
   - Go to: Google Account → Security → 2-Step Verification → App passwords
   - Generate a new app password and use it in `.env`

3. **Start the dev server:**
   ```powershell
   npm run dev
   ```

4. **Open the admin page:**

   Navigate to: http://localhost:3000 (or your deployed Vercel URL)

## 📖 How to Use

### Step 1: Set Up Participants (Admin)

1. Open http://localhost:3000 in your browser
2. Add all participants with their names and email addresses
3. Click "Start Secret Santa & Send Emails"
4. Confirmation emails will be sent to all participants immediately

### Step 2: Participants Reveal (Employees)

1. Each participant receives an email with a unique link
2. They click the link to reveal who they're Secret Santa for
3. The assignment is shown only once (but can be viewed again using the same link)
4. The system tracks who has revealed their assignment

### Step 3: Monitor Status (Admin)

1. The admin dashboard shows:
   - Total participants
   - How many have revealed their assignments
   - Individual status for each participant
2. Use "Refresh Status" to update the dashboard

## 🔒 Security Features

- **Unique Tokens**: 64-character cryptographically secure tokens for each participant
- **No Duplicates**: Each person can only draw one Secret Santa
- **No Self-Assignment**: Algorithm prevents anyone from getting themselves
- **One Link Per Person**: Each token is tied to one participant
- **Server-Side Validation**: All logic runs on the server, not in the browser
- **Reveal Tracking**: System tracks when each person reveals their assignment

## 📁 Project Structure

```
Secret Santa/
├── server.js              # Main Express server with API endpoints
├── package.json           # Node.js dependencies
├── .env                   # Configuration (email, server settings)
├── .env.example           # Example configuration file
├── data.json             # Auto-generated data storage (participants, assignments)
└── public/
    ├── index.html        # Admin interface for setup
    └── reveal.html       # Participant reveal page
```

## 🛠️ API Endpoints

### Admin Endpoints

- `POST /api/admin/setup` - Create Secret Santa and send emails
  ```json
  {
    "participants": [
      {"name": "John Doe", "email": "john@company.com"},
      {"name": "Jane Smith", "email": "jane@company.com"}
    ]
  }
  ```

- `GET /api/admin/status` - Get current status of all participants

- `POST /api/admin/reset` - Reset everything (use with caution!)

### Participant Endpoint

- `GET /api/reveal?token=XXX` - Reveal Secret Santa assignment

## 🎄 Customization

### Change Email Template

Edit the email HTML in `pages/api/admin/setup.js` (search for `mail`) to customize:
- Colors and styling
- Message text
- Company logo

### Change Port

Edit `PORT` in `.env` file:
```env
PORT=8080
APP_URL=http://localhost:8080
```

### Deploy to Vercel

This project is now a Next.js app and is ready for Vercel. Steps:

1. Push to GitHub (if not already):
   ```powershell
   git add .
   git commit -m "Convert to Next.js app"
   git push
   ```

2. Sign in to https://vercel.com and import the GitHub repository. Vercel will detect Next.js automatically.

3. In Vercel project settings -> Environment Variables, add the following:
   - `EMAIL_HOST` (e.g. `smtp.gmail.com`)
   - `EMAIL_PORT` (e.g. `587`)
   - `EMAIL_USER`
   - `EMAIL_PASS` (App Password for Gmail)
   - `APP_URL` (set to your Vercel URL after deploy, e.g. `https://my-app.vercel.app`)

4. Deploy. Vercel will run `npm run build` and host both the frontend and API routes.

Important: the app currently uses a local `data.json` file for storage. On Vercel, serverless functions do not have persistent writable filesystem. For production you should replace `data.json` with a hosted database (e.g. Postgres, Supabase) or another persistent store. This repo keeps `data.json` for simple demos but you must migrate to a DB for reliable production.

## 🐛 Troubleshooting

### Emails not sending?

- Check your email credentials in `.env`
- For Gmail, make sure you're using an App Password
- Check if 2-Step Verification is enabled
- Try allowing "Less secure app access" (not recommended for production)

### "Need at least 2 participants" error?

- Make sure you've added at least 2 people with both name and email

### Token invalid error?

- Tokens are one-time generated during setup
- If you reset the system, old email links won't work
- Participants need to use the link from the most recent email

### Port already in use?

- Change the `PORT` in `.env` to a different number (e.g., 3001)

## 📝 Notes

- The `data.json` file stores all participant data and assignments
- Delete `data.json` to completely reset (or use the Reset button)
- Keep `.env` file secure—never commit it to version control
- For production, consider using a proper database instead of `data.json`

## 🎁 Tips for a Great Secret Santa

- Set a gift budget limit
- Give a deadline for gift exchange
- Consider creating a wishlist system
- Have fun and keep it secret! 🤫

## 📄 License

MIT License - Feel free to use and modify for your company!

---

Made with ❤️ for spreading holiday cheer! 🎄🎅🎁
