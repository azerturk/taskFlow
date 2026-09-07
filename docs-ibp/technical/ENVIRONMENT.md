# Mühit və işə salma

## Qəbul edilmiş platforma

- Windows və Laravel Herd;
- PHP 8.3 və ya daha yeni;
- Composer 2;
- Node.js və npm, lockfile-a uyğun;
- MySQL 8.x tətbiq/compatibility mühiti;
- Vite build və Chromium əsaslı Playwright.

Cari layihə üçün Windows + Herd işləyən qəbul mühitidir. Unix/Linux ayrıca uyğunluq gate-i deyil.

## Əsas fayllar

- `.env.example` — versiyalanan açar nümunələri;
- `.env.testing.example` — versiyalanan, sirr daşımayan dedicated MySQL test connection nümunəsi;
- `.env` — lokal application runtime; commit və sənədə çıxarılmır;
- `.env.testing` — yalnız dedicated test connection override-ları; lokal saxlanılır, Git-ə commit edilmir və sirr göstərilmir;
- `phpunit.xml` — isolated SQLite `:memory:`;
- `phpunit-mysql.xml` — explicit Herd MySQL compatibility;
- `composer.lock`, `package-lock.json` — dependency source of truth.

Norton `phpunit.mysql.xml` adını filesystem filter səviyyəsində blokladığı üçün stable konfiqurasiya adı `phpunit-mysql.xml` seçilib. Composer və architecture guard yalnız bu adı istifadə edir.

## Lokal setup

İlk dəfə dependency və application hazırlığı:

```text
composer install
npm ci
```

`.env` yoxdursa `.env.example` əsasında yaradılır, sonra application key və local DB konfiqurasiya edilir. Real DB migration əmri yalnız hədəf database adı/backup və environment təsdiqləndikdən sonra işlədilir.

Herd-də repository `taskflow-main` adı ilə link olunur və `http://taskflow-main.test` URL-i ilə açılır. Lokal `.env` `APP_URL` dəyəri bu linklə eyni olmalıdır. Vite development üçün `npm run dev`, production asset üçün `npm run build` istifadə olunur.

## Application environment

Minimal kateqoriyalar:

- `APP_*`: environment, URL, key, timezone və debug;
- `DB_*`: application database;
- `SESSION_*`, `CACHE_*`, `QUEUE_*`: host infrastructure;
- `FILESYSTEM_DISK`: private local/object storage seçimi;
- `MAIL_*`: hazırda database notification əsasdır, mail external dependency deyil.

Production-da `APP_DEBUG=false` olmalı, HTTPS/proxy cookie/header konfiqurasiyası deploy qatında yoxlanmalıdır. Credential və key heç vaxt source, log, Activity və sənədə yazılmır.

## MySQL test connection-u

`.env.testing` aşağıdakı prefiksli açarları qəbul edir:

```text
TASKFLOW_MYSQL_TEST_HOST
TASKFLOW_MYSQL_TEST_PORT
TASKFLOW_MYSQL_TEST_DATABASE
TASKFLOW_MYSQL_TEST_USERNAME
TASKFLOW_MYSQL_TEST_PASSWORD
```

İlk quraşdırmada `.env.testing.example` faylı `.env.testing` adı ilə kopyalanır və yalnız lokal dedicated test database credential-ları ilə doldurulur. Nümunə faylı real credential saxlamır.

Database adı `taskflow_test` ilə başlamalıdır. `composer test:mysql` həmin dedicated database-də destruktiv fresh/rollback testləri icra edə bilər; normal application DB adı burada istifadə edilməməlidir.

## Test isolation

Test bootstrap cache, session, view, log və storage yollarını OS temp qovluğunda repository hash-i ilə ayırır. E2E ayrıca disposable SQLite faylı istifadə edir. Buna görə test gate-ləri application `.env` DB-sini migrate etmir.

## Health və smoke

- `/up` Laravel health endpoint-dir.
- `/login` Web authentication entry point-dir.
- `/api/v1/auth/token` credential-to-token endpoint-dir.
- Protected smoke token plaintext-i output/log-a yazmadan `me`, project/task/activity/dashboard və revoke axınını yoxlamalıdır.

## Təhlükəsiz əməliyyat qaydası

- Real `migrate:fresh`, `migrate:refresh`, `migrate:reset` və `db:wipe` unidentified database-də qadağandır.
- Seeder yalnız environment təsdiqi ilə işlədilir; demo hesablar yalnız `local` environment-də yaranır.
- Dependency update ayrıca qərar və lockfile review tələb edir.
- `.env` və `.env.testing` output-u heç vaxt bütöv göstərilmir.
