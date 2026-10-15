# MDM Auto Detail – website

Static one-page site (`index.html` + `img/`) with a small PHP handler (`send.php`) that emails contact and quote form submissions, including photos, to `hassanpourarwin@gmail.com`.

## Deploy to Hostinger

**Option A – Git (auto-deploy from GitHub)**
1. hPanel → Websites → Manage → **Advanced → Git**.
2. Repository: `https://github.com/Mesumraza08-art/mdm.git`, branch `main`, directory: leave empty (deploys to `public_html`).
3. Click **Create**, then **Deploy**. Optionally copy the webhook URL into GitHub → Settings → Webhooks for auto-deploy on push.

**Option B – File Manager / FTP**
Upload everything in this folder (including the hidden `.htaccess`) into `public_html`.

**After deploying**
- hPanel → Security → **SSL**: install the free SSL (the `.htaccess` forces HTTPS).
- hPanel → **Emails**: create `noreply@yourdomain.com` so form mail is sent from your domain and is less likely to land in spam.
- To change the recipient, edit `$TO_EMAIL` at the top of `send.php`.
- Submit a test inquiry with a photo and check the inbox (and spam folder).

## Local preview
```
php -S localhost:8000
```
Pages render without PHP, but the forms need PHP to send.
