# Mühit və işə salma

## Junior üçün: işləyən tətbiqlə test mühitini ayır

Herd lokal PHP tətbiqini işlədən mühitdir; Laravel isə tətbiqin framework-üdür. Bunlar alternativ deyil: TaskFlow Laravel kodu Herd-də işləyir. Qəbul platformamız Windows + Herd-dir.

`.env` işləyən lokal tətbiqin konfiqurasiyasıdır. `.env.testing` ayrıca test üçün ayrılmış connection məlumatını daşıya bilər. `example` faylları yalnız sirrsiz nümunədir; real credential deyil. Bu faylları qarışdırmaq testin yanlış bazaya yönəlməsi riskini yaradır.

SQLite `:memory:` profili test məlumatını yaddaşda hazırlayır. Dedicated MySQL profili isə ayrıca test database-i istifadə edir və onun içindəkiləri silib yenidən qura bilər. «Komandanın adında test var» təkbaşına təhlükəsizlik zəmanəti deyil: faktiki connection, credential icazəsi və environment override-ları vacibdir.

Buradakı komandalar nə etdiyini anlamaq üçündür, hamısını ard-arda işlətmək siyahısı deyil. Xüsusilə `composer setup`, migration/seeder və MySQL/E2E setup hədəfi təsdiqlənmədən işlədilməməlidir. [Test isolation dərsi](../diagrams/flows/test-isolation.md) bu fərqləri nümunə ilə izah edir.

## Qəbul edilmiş platforma

- Windows və Laravel Herd;
- runtime müqaviləsi `composer.json` üzrə PHP `^8.3`; cari locked development/test dependency-ləri üçün PHP **8.4.1 və ya daha yeni 8.x**;
- Composer 2;
- Node.js və npm, lockfile-a uyğun;
- MySQL 8.x tətbiq/compatibility mühiti;
- Vite build və Chromium əsaslı Playwright.

Cari layihə üçün Windows + Herd işləyən qəbul mühitidir. Unix/Linux ayrıca uyğunluq gate-i deyil.

`composer.lock` daxilində Laravel `^8.3`, Pest `^8.4`, PHPUnit isə `>=8.4.1` tələb edir. Buna görə junior development mühitində yalnız PHP 8.3 seçilməsi tam `composer install`/test alətlərinə yetmir. Lockfile dependency versiyalarının, faktiki Herd PHP seçimi isə işlədilən executable-ın mənbəyidir; bu ikisi birlikdə yoxlanmalıdır.

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

`composer setup` qısa, təhlükəsiz read-only yoxlama deyil: script dependency install, key generation və `migrate --force` daxil edir. Hədəf application DB-ni təsdiqləmədən bu script-i işlətmə. R1 modulları provider-lərlə qeydiyyatdadır; real `migrate` təsdiqlənəndə onların iki cədvəli də yaranacaq. Bu sənəd dəyişiklik işi özü migration/seeder icra etmir.

## Application environment

Minimal kateqoriyalar:

- `APP_*`: environment, URL, key, timezone və debug;
- `DB_*`: application database;
- `SESSION_*`, `CACHE_*`, `QUEUE_*`: host infrastructure;
- `FILESYSTEM_DISK`: Laravel-in default disk seçimi; cari Media ayrıca `Modules/Media/config/config.php` daxilində `media.disk=local` istifadə edir. Tək bu environment dəyişəni Media-nı object storage-a keçirmir;
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

Database adı dəqiq `taskflow_test` və ya `taskflow_test_<kiçik hərf/rəqəm/alt xətt>` formasında olmalıdır. `composer test:mysql` həmin dedicated database-də destruktiv fresh/rollback testləri icra edə bilər; normal application DB adı burada istifadə edilməməlidir. Prefiks yoxlaması `tests/bootstrap/mysql.php` daxilindədir; test credential-ları yalnız dedicated bazaya icazə verməlidir.

Destruktiv MySQL setup-dan əvvəl prosesdə `DB_URL` boş olmalıdır: Laravel connection URL-si ayrıca connection parametrlərini üstələyə bilər və cari MySQL bootstrap onu özü sıfırlamır. Əvvəlki MySQL qəbul run-unda `DB_URL` proses səviyyəsində boşaldılıb; `.env` faylı dəyişdirilməyib. `.env.testing`-ə yalnız nümunədəki `TASKFLOW_MYSQL_TEST_*` açarlarını yaz; application connection-u və secret-ləri əlavə etmə. Test credential-larının icazəsi dedicated bazaya məhdud olmalıdır; prefiks yoxlaması bu şərtləri əvəz etmir.

## Test isolation

Test bootstrap cache, session, view, log və storage yollarını OS temp qovluğunda repository hash-i və profil adı ilə ayırır. E2E-nin disposable SQLite faylı dəqiq `TaskFlowTestEnvironment::temporaryRoot('e2e').'/database.sqlite'` yoludur; `tests/e2e/database.sqlite` deyil. Yolu `tests/bootstrap/TestEnvironment.php`, hazırlığı `tests/e2e/setup.php`, serveri isə `tests/e2e/serve.php` müəyyən edir.

Destruktiv E2E setup-dan əvvəl də prosesdə `DB_URL` boş olmalı, `.env.testing` yalnız prefiksli test açarlarını saxlamalı, test credential-ları yalnız dedicated bazaya icazə verməlidir. Cari E2E setup inherited `DB_URL`-u sıfırlamır; server launcher sıfırlayır, amma server yalnız setup-dan sonra işləyir. Bu səbəbdən temp SQLite path guard-ı təkbaşına bütün environment override-larına qarşı «application bazasına heç vaxt toxunmaz» zəmanəti deyil. Düzgün konfiqurasiya və faktiki connection təsdiqi saxlanılmalıdır.

Eyni profilin iki run-ı eyni temp yeri və MySQL bazasını paylaşa bilər; paralel başladılmamalıdır.

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
