# Test və keyfiyyət strategiyası

## Qat modeli

Pest əsas test framework-üdür. Playwright yalnız real browser, responsive layout, cookie/CSRF, JavaScript və progressive enhancement journey-lərini tamamlayır.

```text
tests/
├── Unit
├── Feature
├── Architecture
└── e2e

Modules/{Projects,Tasks,Media,Activity,Dashboard}/tests/{Unit,Feature}
```

- Unit: enum/transition, rank, recipient, sanitizer və pure helper qaydaları.
- Repository/integration: visibility, filter/sort/pagination, constraint, lock və migration.
- Feature: Web/API/security/authorization/Activity/notification.
- Livewire: yalnız dörd təsdiqlənmiş component-in validation və service parity-si.
- Architecture: qat və module sərhədləri.
- Playwright: 10 kritik journey, desktop və mobile olmaqla 20 icra.

## SQLite profili

Default `phpunit.xml` profili:

- SQLite `:memory:`;
- array cache/session/mail;
- sync queue;
- deterministic test APP_KEY;
- OS-dan asılı olmayan `sys_get_temp_dir()` storage/cache/view yolları;
- root və bütün beş modulun migration/test discovery-si.

Bu profil `.env` və production credential-larını istifadə etmir.

## MySQL profili

`phpunit-mysql.xml` eyni 247 testi Herd MySQL-də işlədir. Bootstrap yalnız `.env.testing` daxilindəki `TASKFLOW_MYSQL_TEST_*` açarlarını oxuyur və database adı `taskflow_test` prefix-inə uyğun olmayanda fail-closed dayanır.

MySQL profili fresh migration, JSON/Activity query, constraint, issue/rank locking davranışı və tam rollback-i real MySQL semantics ilə yoxlayır. Cari layihə üçün qəbul edilmiş runtime Windows + Herd-dir; ayrıca Unix/Linux gate-i tələb olunmur.

## Canonical komandalar

```text
composer test
composer test:unit
composer test:architecture
composer test:static
composer test:mysql
composer format:check
npm run build
npm run e2e:list
npm run e2e
```

Əlavə locked dependency yoxlamaları:

```text
composer validate --no-check-publish --no-interaction
composer install --dry-run --no-interaction
npm ci --ignore-scripts --dry-run
```

`npm run e2e` yalnız disposable `tests/e2e/database.sqlite` fixture-i üzərində setup edir; real application DB-yə toxunmur. `test-results` və `playwright-report` Git-dən kənardır.

## Test məsuliyyətləri

- Hər mutation üçün positive, validation, authorization, state conflict və lazım olduqda Activity/notification assertion-u olmalıdır.
- Critical matrix-lər global role × project role × reporter/assignee/watcher/outsider, ability × policy, project lifecycle və media/comment ownership-i əhatə edir.
- Filter test-ləri inaccessible və nonexistent ID-lərin eyni safe nəticə verməsini sübut edir.
- Query-budget test-ləri Dashboard, workspace header, detail və notification presentation-da N+1-i bloklayır.
- Multi-file media test-ləri validation/storage/association/Activity failure-larında bütün request compensation-ını yoxlayır.
- Architecture mutation fixture-ləri həm `\` həm `/` separator formalarını yoxlayır.
- Skipped/focused/flaky test release sübutu sayıla bilməz.

## Playwright journey-ləri

1. Login/logout və unauthorized redirect.
2. Admin user create/suspend və login denial.
3. Manager project create/activate/member idarəsi.
4. Member Bug report, manager assignment, assignee Livewire progress.
5. Backlog reorder və board drag/drop persistence/workflow.
6. Label/filter URL state və responsive task list.
7. Watch/comment/notification.
8. Multi-media upload, image/PDF preview, private download və delete.
9. Completed/Archived read-only UI.
10. Mobile navigation, keyboard focus, Escape/modal və JS-disabled core form.

Hər journey desktop və mobile project-də icra olunur. Edge case-lər Playwright-a daşınmır, Pest-də qalır.

## Son qəbul nəticəsi

Tarix, runtime, dəqiq test/assertion sayı və digər gate nəticələri [`RELEASE_BASELINE.md`](RELEASE_BASELINE.md) sənədindədir.

