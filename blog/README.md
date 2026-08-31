# Exoshatter Blog — Deployment Guide

## 1. Files

Upload everything in this folder to `/blog/` on `exoshatter.com`:

```
db.php  header.php  footer.php  style.css
register.php  verify.php  login.php  logout.php
create_post.php  index.php  test_db.php
```

Also make sure `/img/exologo.png` exists at the site root (outside `/blog/`).

## 2. Database (Termux side)

Start MariaDB and keep it running:

```bash
mysqld_safe &
```

Create the schema (once):

```bash
mysql -u root -p < schema.sql   # or paste the CREATE TABLE statements manually
```

**Set a real MariaDB root/app-user password** if you haven't — an
unauthenticated `root` account is fine for local-only testing but this
database is about to be reachable (via the tunnel) from your web server
process, so give it a real password and, ideally, a dedicated
`exoblog_app` user with grants limited to the `exoblog` database instead
of using `root`.

## 3. Reverse SSH tunnel

From Termux:

```bash
ssh -N -R 3306:127.0.0.1:3306 server_user@exoshatter.com
```

This only stays up as long as that SSH session is alive — if Termux is
killed by Android's battery optimizer, or the connection drops, your
site's database access goes down with it. Two things worth setting up:

- **`autossh`** instead of plain `ssh`, so the tunnel reconnects
  automatically on drops:
  ```bash
  pkg install autossh
  autossh -M 0 -N -R 3306:127.0.0.1:3306 server_user@exoshatter.com
  ```
- **Termux:Boot / wake lock**, so Android doesn't suspend the session —
  run `termux-wake-lock` in the session, and consider Termux's
  `sshd`/boot scripts so the tunnel restarts if the phone reboots.

On the server side, confirm `sshd_config` has `GatewayPorts` set
appropriately (default `no` is fine since the app connects to
`127.0.0.1:3306` locally — you don't want the forwarded port exposed to
the whole internet).

## 4. Test connectivity

Once the tunnel is up and files are deployed:

```
https://exoshatter.com/blog/test_db.php
```

You should see the MariaDB version, table list, and row counts. **Delete
or restrict `test_db.php` after confirming it works** — it doesn't
require login and shouldn't stay publicly reachable indefinitely.

## 5. Set the DB password

`db.php` reads `EXOBLOG_DB_PASS` from the environment first, falling back
to the placeholder `'MARIADB_PASSWORD'` in the file. Set the real
password as an environment variable in your web server config rather
than editing the fallback in place, so it isn't sitting in a file that
might end up in a git repo or backup. If your host doesn't give you an
easy way to set env vars, at minimum move the password into a file
outside the web root and `require()` it.

## 6. Outbound email (registration)

`register.php` calls PHP's `mail()` to send the verification link. Many
shared hosts need this configured (or you'll want to switch to SMTP via
something like PHPMailer) before real verification emails will arrive.
For initial testing without mail configured, set:

```
EXOBLOG_DEBUG=1
```

as an environment variable, and the registration success message will
also show the verification link directly on the page.

## Notes on what's already handled

- Passwords are hashed with `password_hash()` / verified with
  `password_verify()` — bcrypt, matching your schema.
- Every query uses PDO prepared statements (no string-concatenated SQL).
- CSRF tokens are checked on register/login/create-post form submissions.
- Session ID is regenerated on login.
- Login timing doesn't reveal whether an email is registered (it always
  runs `password_verify()`, even for unknown emails).
- All user-supplied output is escaped with `htmlspecialchars()`.
