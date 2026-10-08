# Test və keyfiyyət strategiyası

## Junior üçün: testdə nəyi oxuyuruq?

Test kiçik, təkrarlana bilən ssenaridir: **əvvəl vəziyyəti qurur**, **bir əməliyyat edir**, **sonra gözlənilən nəticəni yoxlayır**. Məsələn, status testində aktiv layihə və assignee qurulur, status change çağırılır, yeni status/versiya/Activity və ya gözlənilən conflict yoxlanır.

**Assertion** «bu nəticə belə olmalıdır» yoxlamasıdır. **Regression testi** isə tapılmış səhvin geri qayıtmamasını qoruyan ssenaridir. Testin yaşıl olması yalnız yazılmış yoxlamaların keçməsidir; bütün mümkün bugların yoxluğu deyil.

Unit kiçik qaydanı ayrıca, Feature girişdən nəticəyə davranışı, Integration real hissələrin birgə işini, Architecture isə kod sərhədini yoxlayır. Playwright browser-də görünüş və interaction-a baxır. Eyni sualı hamısında təkrar etməkdənsə düzgün qatı seçmək lazımdır.

Testi ilk dəfə oxuyanda `it(...)`/`test(...)` adını, setup-ı, action-u və `expect`/HTTP assertion-larını tap. Sonra həmin action-un real service-inə keç. R1 after-commit kimi hallarda testin özü outer transaction açıb-açmadığı da nəticəyə təsir edir.

Ayrı dərslər: [test qatları](../extended/test-types.md), [Pest və PHPUnit](../extended/phpunit-vs-pest.md), [real commit testlərinin fərqi](../extended/refresh-database-vs-database-migrations.md), [təhlükəsiz test bazası](../diagrams/flows/test-isolation.md). Aşağıdakı əmrlər üçün mühit/icazə qaydasını da oxu.

## Qat modeli

Pest PHPUnit üzərində işləyən əsas test yazma/işə salma qatıdır; `phpunit*.xml` konfiqurasiyaları bununla ziddiyyət yaratmır. Playwright real browser, responsive layout, cookie/CSRF, JavaScript və JavaScript-siz fallback ssenarilərini tamamlayır. Sadə müqayisə: [PHPUnit və Pest](../extended/phpunit-vs-pest.md).

```text
tests/
├── Unit
├── Feature
├── Architecture
└── e2e

Modules/{Projects,Tasks,Media,Activity,Dashboard}/tests/{Unit,Feature}
Modules/{LearningCatalog,LearningInsights}/tests/Feature
Modules/LearningInsights/tests/Integration
```

- Unit: enum/transition, rank, recipient, sanitizer və pure helper qaydaları.
- Repository/integration: visibility, filter/sort/pagination, constraint, lock və migration.
- Feature: Web/API/security/authorization/Activity/notification.
- Livewire: yalnız dörd təsdiqlənmiş component-in validation və service parity-si.
- Livewire transport təhlükəsizliyi: real page snapshot + real update HTTP request ilə persistent middleware, suspend və membership revocation regression-ları.
- Architecture: qat və module sərhədləri.
- Playwright: 10 kritik journey, desktop və mobile olmaqla 20 icra.

## SQLite profili

Default `phpunit.xml` profili:

- SQLite `:memory:`;
- array cache/session/mail;
- sync queue;
- deterministic test APP_KEY;
- OS-dan asılı olmayan `sys_get_temp_dir()` storage/cache/view yolları;
- kök, beş məhsul modulu və iki learning modulunun açıq migration/test discovery-si; lab-larda ayrıca Unit qovluğu yoxdur.

Bu profil `.env` və production credential-larını istifadə etmir.

## MySQL profili

`phpunit-mysql.xml` eyni test inventarını Herd MySQL-də işlədir. `tests/bootstrap/mysql.php` lokal `.env.testing` faylını yükləyir, `TASKFLOW_MYSQL_TEST_*` açarlarını `DB_*` parametrlərinə map edir. Database adı yalnız `taskflow_test` və ya `taskflow_test_<kiçik hərf/rəqəm/alt xətt>` formasında ola bilər; uyğun deyilsə bootstrap dayanır. `.env.testing` faylında application `DB_*`/`DB_URL` credential-ları saxlanmamalıdır; nümunədəki prefiksli test açarları istifadə edilməlidir.

Destruktiv MySQL test setup-ından əvvəl prosesdə `DB_URL` boş olmalı, `.env.testing` yalnız prefiksli test açarlarını saxlamalı və test credential-ının icazəsi dedicated bazaya məhdud olmalıdır. Bootstrap miras connection URL-sini özü sıfırlamır; URL ayrıca `DB_*` parametrlərini üstələyə bilər. Ad prefiksi yoxlaması bu environment şərtini əvəz etmir.

MySQL profili fresh migration, JSON/Activity query, constraint, issue/rank locking davranışı və tam rollback-i real MySQL semantics ilə yoxlayır. Cari layihə üçün qəbul edilmiş runtime Windows + Herd-dir; ayrıca Unix/Linux gate-i tələb olunmur.

Eyni repository/profil üçün paralel iki test prosesi işlətmə: bootstrap temp yolu profil və repository hash-i ilə ayrılır, proses ID-si ilə deyil. MySQL dedicated DB də həmin profil üçün paylaşılır.

## R1 commit və bərpa testləri

`tests/Pest.php` Feature qovluqlarına `RefreshDatabase`, yalnız `Modules/LearningInsights/tests/Integration` qovluğuna `DatabaseMigrations` tətbiq edir. Feature testinin xarici test transaction-u real commit-i təxirə sala bilər. Buna görə real after-commit sübutu ayrıca integration qatındadır; burada `Event::fake()` ilə listener əlaqəsi gizlədilmir.

| Fayl | Sübut etdiyi davranış |
|---|---|
| `LearningCatalogPublishSpecificationTest.php` | Title trim/Unicode sərhədləri, rollback, event payload-u, public feed sırası |
| `LearningInsightsProjectionSpecificationTest.php` | Entry idempotentliyi, metadata-nın dəyişməməsi, event UUID konflikti, rebuild boş/feed-failure halları |
| `R1LearningFlowTest.php` | Provider ilə real listener, outer commit/rollback, listener failure-dan recovery, yarımçıq rebuild rollback-i, migration down/up |
| `tests/Architecture/R1LearningBoundaryTest.php` | Üç public Catalog type-ı, reverse dependency, table ownership, qat və UI/route sərhədləri; alias/FQCN fixture-ləri |

R1-də HTTP/UI yoxdur; yeni Playwright journey-si əlavə edilməyib. Bu testlər outbox, queue retry və ya exactly-once delivery sübutu deyil — həmin mexanizmlər implementasiya edilməyib.

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

`npm run e2e` üçün nəzərdə tutulan disposable SQLite faylı repository daxilində deyil: `TaskFlowTestEnvironment::temporaryRoot('e2e').'/database.sqlite'` yolundadır. `tests/bootstrap/TestEnvironment.php` onu OS temp qovluğunda repository hash-i və `e2e` profil adı ilə ayırır. `tests/e2e/setup.php` həmin faylı hazırlayıb `migrate:fresh` edir; `tests/e2e/serve.php` eyni fayldan test serverini başladır.

**Destruktiv E2E setup-dan əvvəl** prosesdə `DB_URL` boş olmalıdır; `.env.testing` yalnız prefiksli `TASKFLOW_MYSQL_TEST_*` açarları saxlamalı, oradakı credential-lar yalnız dedicated test bazasına icazə verməlidir. Cari `setup.php` inherited `DB_URL`-u sıfırlamır, `serve.php` isə sıfırlayır. Serverin qoruması daha əvvəl işləyən setup-ı retroaktiv qorumur. Buna görə «real application DB-yə heç bir halda toxunmur» unconditional zəmanəti verilmir; düzgün environment və hədəf connection təsdiqi vacibdir. `test-results` və `playwright-report` Git-dən kənardır.

Bu komandalar avtomatik icazə deyil: dependency əməliyyatları, real browser automation və real DB əməliyyatları repository səlahiyyət qaydasına uyğun ayrıca təsdiq tələb edir. Runtime/test PHP fərqi [ENVIRONMENT.md](ENVIRONMENT.md) daxilindədir.

## Test məsuliyyətləri

- Hər mutation üçün positive, validation, authorization, state conflict və lazım olduqda Activity/notification assertion-u olmalıdır.
- Critical matrix-lər global role × project role × reporter/assignee/watcher/outsider, ability × policy, project lifecycle və media/comment ownership-i əhatə edir.
- Filter test-ləri inaccessible və nonexistent ID-lərin eyni safe nəticə verməsini sübut edir.
- Query-budget test-ləri Dashboard, workspace header, detail və notification presentation-da N+1-i bloklayır.
- Project key regression-u backend canonical normalizasiya ilə yanaşı JS aktiv və JS-siz native browser constraint validation səviyyəsində yoxlanır.
- Task detail watcher regression-u self-watch/unwatch, manager target idarəsi və read-only lifecycle görünüşünü yoxlayır.
- Livewire status regression-u uğurlu mutation-dan sonra full redirect, yeni header statusu və yeni Activity görünüşünü yoxlayır.
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
