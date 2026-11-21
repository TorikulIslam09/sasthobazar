# Bangla text (UTF-8) – checks, fixes and correct imports

Bangla needs **utf8mb4** everywhere: the database connection, the database, every table and column, the files and the page header. SasthoBazar already does all of this:

| Where | What is set | File |
|---|---|---|
| PDO connection | `charset=utf8mb4` in the DSN **and** `SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci` | `core/Database.php` (constructor), `config/database.php` |
| Database / tables / columns | `utf8mb4` / `utf8mb4_unicode_ci` (JSON columns `utf8mb4_bin`, which is normal) | `database/schema.sql`, installer, migration `000010_utf8mb4` |
| Page | `<meta charset="utf-8">` first in `<head>` + `Content-Type: text/html; charset=UTF-8` header | `app/views/partials/head.php` (used by every layout), `core/bootstrap.php` |
| Files | all `.php` / `.sql` saved as UTF-8 **without BOM** | checked by `database/check_utf8.php` |
| MySQL server (XAMPP) | `character-set-server=utf8mb4`, `default-character-set=utf8mb4` | `C:\xampp\mysql\bin\my.ini` |

## "?????" instead of Bangla

`?????` means the text was **saved** as question marks: somewhere on the way in it passed through something that was not UTF-8. Examples are a latin1 table, an import with the wrong character set, or a Windows terminal. The original letters are not stored anywhere any more, so **no setting can show them again**. They must be put back.

1. Check: `C:\xampp\php\php.exe database\check_utf8.php` lists anything that is not utf8mb4 and every column that still contains `????`.
2. Structure wrong (latin1 / utf8 tables)? Run `C:\xampp\php\php.exe database\migrate.php` (migration `000010_utf8mb4` converts them). Without a terminal, run `database/fix_utf8mb4.sql` in phpMyAdmin (generate a fresh copy for your database with `check_utf8.php --sql`).
3. Restore the seeded texts (site name, tagline, address, role, category and page names, districts …): `C:\xampp\php\php.exe database\repair_text.php` shows what it would change; add `--apply` to change it. It copies the original values from `seed.sql`, and only where the current value contains `??`.
4. Anything typed in later (products, shop names, descriptions) that the check still lists must be typed in again in the admin. It will now save correctly.

## Importing a .sql file correctly

> Importing `seed.sql` again into a database that already has data gives "Duplicate entry" errors. It is only for a **new, empty** database (the installer does it for you). To fix a few broken texts, use `repair_text.php` instead.

### phpMyAdmin
1. **New** → database name `sasthobazar`, collation **utf8mb4_unicode_ci** → Create. (For an existing database: *Operations* → Collation `utf8mb4_unicode_ci` → tick "Change all tables collations" → Go.)
2. Select the database on the left → **Import**.
3. File: `database/schema.sql` first, then `database/seed.sql` (or a backup `.sql.gz`).
4. **Character set of the file: utf-8** (never latin1 / windows-1252). Format: SQL. Leave "Enable foreign key checks" unticked for backups → **Go**.
5. Then run `database/migrate.php` (or `fix_utf8mb4.sql` in the SQL tab).

### Command line
```bash
# Windows (XAMPP)
C:\xampp\mysql\bin\mysql.exe -u root -e "CREATE DATABASE sasthobazar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci"
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root sasthobazar < database\schema.sql
C:\xampp\mysql\bin\mysql.exe --default-character-set=utf8mb4 -u root sasthobazar < database\seed.sql
C:\xampp\php\php.exe database\migrate.php

# Linux server, a backup made by the app
gunzip < sasthobazar-YYYYMMDD-HHMMSS-....sql.gz | mysql --default-character-set=utf8mb4 -u USER -p sasthobazar

# exporting by hand
mysqldump --default-character-set=utf8mb4 --single-transaction -u USER -p sasthobazar > backup.sql
```
In PowerShell use `cmd /c "mysql ... < file.sql"`; PowerShell's own `<` / `Get-Content |` can change the encoding.

## Editing files
Save every `.php` / `.sql` file as **UTF-8 (without BOM)**:
- VS Code: bottom right "UTF-8" → *Save with Encoding* → UTF-8.
- Notepad++: *Encoding → UTF-8*, not "UTF-8-BOM".

Never use Windows Notepad's "ANSI".

## Test
1. `C:\xampp\php\php.exe database\check_utf8.php` ends with **"Everything is UTF-8 (utf8mb4)."**
2. Open the shop in বাংলা: the shop name, footer tagline, address, categories and product names show Bangla letters.
3. Admin → Settings → General: type a Bangla tagline, save, reload. The same text comes back. Check the footer.
4. Admin → a product: put Bangla in the name, save, open the product page.
5. Browser → View page source: the first line inside `<head>` is `<meta charset="utf-8">`. Dev tools → Network → the page → Response headers: `Content-Type: text/html; charset=UTF-8`.
