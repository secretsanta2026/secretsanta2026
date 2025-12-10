# 🎅 Secret Santa - Ready to Upload

## ✅ What's Ready

This `secretsanta` folder is ready to upload to your InfinityFree website.

## 🚀 How to Deploy

### Step 1: Change Password
1. Open `index.php`
2. Find line 8: `define('ADMIN_PASSWORD', 'admin123');`
3. Change to your secure password
4. Save

### Step 2: Upload to InfinityFree
1. Login to InfinityFree control panel
2. Open File Manager or use FTP
3. Navigate to `htdocs` folder (or `public_html`)
4. Upload the entire `secretsanta` folder
5. Done!

### Step 3: Access Your App
Your app will be at:
```
https://yourdomain.infinityfreeapp.com/secretsanta/
```
or
```
https://yourdomain.com/secretsanta/
```

Admin panel: `https://yourdomain.com/secretsanta/index.php`
Reveal links will be: `https://yourdomain.com/secretsanta/reveal.php?token=...`

## 📧 Email Setup

InfinityFree supports PHP `mail()` function, so emails should work automatically!

**Important**: InfinityFree has email sending limits:
- Max 100 emails per hour
- Max 500 emails per day

For larger groups, consider splitting the setup.

## 📁 Files Included

- `index.php` - Admin panel
- `reveal.php` - Reveal page  
- `.htaccess` - Security
- `README.md` - This file

The `data.json` file will be created automatically when you setup.

## 🔐 Security

- Change admin password before using
- `.htaccess` protects data.json from direct access
- Unique 64-character tokens for each participant

## 🎯 Usage

1. Go to `yourdomain.com/secretsanta/`
2. Enter admin password
3. Add participants
4. Click "Setup & Send Emails"
5. Everyone receives their unique link!

## ⚠️ Important Notes

- **Must change admin password** in `index.php` line 8
- Works best with 2-100 participants (due to email limits)
- `data.json` auto-creates with proper permissions
- Email links automatically include `/secretsanta/` path

## 🆘 Troubleshooting

**Can't access the site?**
→ Make sure folder is in `htdocs` or `public_html`

**Emails not sending?**
→ InfinityFree may require email verification first
→ Check your InfinityFree email settings

**"Permission denied" for data.json?**
→ InfinityFree should set this automatically
→ If not, use File Manager to set folder permissions to 755

**404 errors?**
→ Make sure you uploaded to correct folder
→ URL should be: `yourdomain.com/secretsanta/index.php`

## 🎄 Ready!

Your Secret Santa app is ready to upload and use immediately!