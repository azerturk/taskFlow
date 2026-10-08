# Qəbul edilmiş versiya və yoxlama sübutları

## Junior üçün: bu cədvəllər nə deyir, nə demir?

Bu fayl müəyyən tarixdə hansı yoxlamanın işlədildiyini və nəticəsini saxlayır. «PASS» həmin run-da həmin gate-in keçdiyini deyir; bundan sonra edilən bütün dəyişikliklərin avtomatik yoxlandığını demir.

Test sayı ssenari sayıdır, assertion sayı isə onların içindəki konkret yoxlamaların sayıdır. Məsələn, bir status testi həm statusu, həm versiyanı, həm audit qeydini yoxlaya bilər. Daha çox say özü-özünə daha keyfiyyətli test demək deyil; əhatə və düzgün failure ssenarisi vacibdir.

SQLite, dedicated MySQL, browser E2E və SVG render fərqli şeyləri yoxlayır. Diagramın düzgün görünməsi tətbiqin login/media axınının browser-də test edildiyi demək deyil. «İşlədilməyib» qeydi nəticəni gizlətməmək üçündür.

Əvvəl son uyğun tarixli bölməni, sonra onun əhatə və məhdudiyyətlərini oxu. Öz mühitində command işlətməzdən əvvəl [test strategiyasını](TESTING.md) və [isolation dərsini](../diagrams/flows/test-isolation.md) oxu; bu tarixçə avtomatik əməliyyat icazəsi deyil.

## Qəbul statusu

2026-09-07 (Asia/Baku) tarixində roadmap-xarici TaskFlow implementasiyası Windows + Laravel Herd qəbul mühitində tam avtomatlaşdırılmış gate-lərdən keçib. Açıq implementation task/status sənədi saxlanılmır; bu fayl son tam release baseline-ını və ondan sonrakı incremental yoxlamaları saxlayır.

## 2026-10-08 junior izahlarının ayrıca yoxlaması

İlkin diagram sənədləşməsindən sonra oxunaqlılıq üçün ayrıca keçid edilib. 27 SVG saxlanılıb, hər birinin yanında eyni adlı geniş Markdown dərsi yaradılıb. 25 mövcud concept məqaləsi sadə ssenarilər/kod izahları ilə genişləndirilib, HTTP xətaları və nested resource üçün 2 ayrıca məqalə əlavə edilib. Əsas business/technical/modul/lab sənədlərinə sadə giriş və uyğun dərs keçidləri əlavə olunub.

| Yoxlama | Nəticə |
|---|---|
| Lokal Markdown keçidləri, anchor və fayl yolunun hərf registri | PASS — 82 Markdown, 1 089 keçid, 0 qırıq keçid, 0 indeksdən kənar fayl |
| Diagram → ayrıca izah uyğunluğu | PASS — 27 SVG üçün 27 eyni adlı Markdown dərsi |
| SVG-lərin bu keçiddə dəyişməməsi | PASS — əvvəl/sonra 27 SHA-256 hash eynidir |
| Kod nümunələri və davranış izahı | Source və uyğun test ssenariləri ilə oxuyaraq tutuşdurulub; bu, yeni runtime test run-u deyil |
| Markdown code fence/UTF-8 və diff whitespace | PASS |

Bu keçiddə application kodu, authorization davranışı, database schema/migration, dependency, environment və roadmap dəyişdirilməyib. Tam SQLite/MySQL suite, build və browser E2E yenidən işlədilməyib: dəyişiklik mətn sənədləridir. SVG vizual render-i də təkrarlanmayıb; şəkillər dəyişməyib və əvvəlki render yoxlaması aşağıdakı ilkin sənəd keçidində ayrıca qeyd olunur.

Source review Dashboard overdue summary-si ilə queue/API siyahısında `today`/`now` sərhəd fərqini təsdiqləyib. Bu mövcud uyğunsuzluq business/API/modul və sadə Dashboard dərsində açıq göstərilib; kodda düzəldilmiş kimi təqdim olunmur. Əvvəlki tarixli test sayları həmin run-lara aiddir və bu sənəd yoxlaması ilə əvəz edilmir.

## 2026-10-08 sənəd uyğunluğu yoxlaması

Cari kod həqiqət mənbəyi götürülərək `docs-ibp` yeniləndikdən sonra aşağıdakı məqsədli yoxlamalar aparılıb. Tətbiq kodu, dependency, environment və migration faylları dəyişdirilməyib; bu yoxlama yeni tam məhsul release-i deyil.

| Yoxlama | Nəticə |
|---|---|
| Architecture + runtime API route/sənəd müqaviləsi, SQLite | PASS — 97 test, 233 assertion; 13 796 ms |
| Markdown keçidləri, anchor və yolun hərf registri | PASS — 52 sənəd, 529 keçid, 0 qırıq keçid, 0 indeksdən kənar fayl |
| SVG XML, dil/başlıq/təsvir və təhlükəsiz lokal mənbə | PASS — 27 SVG; remote asset, script və foreignObject yoxdur |
| Lokal headless Chromium render-i və vizual baxış | PASS — 27 SVG; mətn daşması və üst-üstə düşmə düzəldilib |
| Diff whitespace yoxlaması | PASS |

Bu işdə tam SQLite/MySQL suite, frontend build və tətbiqin browser E2E-si təkrar işlədilməyib: dəyişiklik yalnız sənədləşmədir. SVG render yoxlaması tətbiqin browser acceptance-i deyil. Əvvəlki implementasiya run-ları aşağıda ayrıca tarixli sübut kimi saxlanır. Kodbazanın mövcud key-guard, cleanup və connection URL məhdudiyyətləri sənədləşdirilib, kodda düzəldilmiş kimi təqdim edilmir.

## 2026-10-08 R1 praktikası üzrə əlavə yoxlama

LearningCatalog/Insights implementasiyası tamamlanan əvvəlki işdə aşağıdakı nəticələr alınıb. Bu, hazırkı **sənədləşmə işi zamanı yeni test run-ı deyil**; həmin implementasiya yoxlamasının tarixli sübutudur. Məhsulun beş modulu dəyişdirilməyib, iki izolyasiya edilmiş tədris modulu əlavə edilib.

| Yoxlama | Qeydə alınmış nəticə |
|---|---|
| Tam SQLite Pest | PASS — 355 test, 2 190 assertion; 218 321 ms |
| Tam dedicated Herd MySQL Pest | PASS — 355 test, 2 190 assertion; 342 543 ms |
| Architecture | PASS — 94 test, 184 assertion |
| Static/API contract | PASS — 97 test, 233 assertion |
| Məqsədli learning Feature + Integration | PASS — 31 test, 190 assertion |
| PHP format, frontend build, diff/sənəd-config uyğunluğu | PASS |
| Yeni Playwright run-ı | İşlədilməyib — R1-də HTTP/UI axını yoxdur |
| Real application migration/seeder | İşlədilməyib — migration-lar yalnız test bazalarında yoxlanıb |

Yeni integration qatında real provider/listener əlaqəsi, outer commit/rollback, listener xətasından sonra rebuild və yarımçıq rebuild rollback-i yoxlanıb. Queue, outbox/inbox və avtomatik retry implementasiya edilməyib; test nəticələri belə zəmanətlər vermir. Bütöv R1 roadmap tamamlanmış sayılmır, yalnız iki modullu praktika tamamlanıb.

MySQL run-unda miras `DB_URL` proses səviyyəsində boşaldılıb və dedicated `taskflow_test*` profilindən istifadə edilib; `.env` dəyişdirilməyib, credential-lar output-a çıxarılmayıb. Aşağıdakı 2026-09-07/2026-10-01 nəticələri tarixi sübut kimi saxlanır, cari run sayları ilə əvəz olunmur.

## 2026-10-01 incremental yoxlama

Junior hesabatlarından təsdiqlənən Livewire active-user sərhədi, Project Key browser müqaviləsi, task-detail watcher UI-sı, status səhifə sinxronizasiyası və label keçidi implementasiya edildikdən sonra aşağıdakı nəticələr alınıb:

| Gate | Nəticə |
|---|---|
| Tam SQLite Pest | PASS — 254/254 test, 1905 assertion |
| Tam dedicated Herd MySQL Pest | PASS — 254/254 test, 1905 assertion |
| Məqsədli regression + query budget | PASS — 23/23 test, 158 assertion |
| Architecture | PASS — 24/24 test, 89 assertion |
| Static/security/route | PASS — 27/27 test, 138 assertion |
| PHP format | PASS |
| Production asset | PASS — Vite 8.2.1 |
| Playwright inventory | PASS — 10 journey × desktop/mobile = 20 |

Project Key və watcher/status journey-lərinin Playwright mənbəyi yenilənib, lakin real browser automation bu incremental run-da ayrıca icazə olmadığı üçün yenidən işə salınmayıb. Buna görə aşağıdakı 2026-09-07 nəticəsi son **tam** browser daxil release baseline-ı olaraq qalır; 2026-10-01 kod vəziyyətinin non-browser gate-ləri tam yaşıldır, yeni browser acceptance-i isə hələ təsdiqlənməyib.

## 2026-09-07 qəbul mühiti

| Komponent | Versiya/nəticə |
|---|---|
| OS | Windows NT 10.0.26200 x64 |
| PHP | 8.4.24 NTS, Herd PHP executable |
| Laravel | 13.25.0 |
| Composer | 2.9.8 |
| Node.js | 24.19.0 |
| npm | 11.17.0 |
| Vite | 8.2.1 |
| Default test DB | SQLite `:memory:` |
| Compatibility DB | Dedicated Herd MySQL, `taskflow_test*` fail-closed adı |
| Herd site | `http://taskflow-main.test` |

Runtime tələbi `composer.json` üzrə PHP `^8.3`-dür; locked Pest/PHPUnit ilə development/test üçün PHP 8.4.1+ tələb olunur. Bu tarixi qəbul run-u PHP 8.4.24-də aparılıb. Unix/Linux ayrıca gate deyil.

## 2026-09-07 tam yoxlama nəticələri

| Gate | Komanda | Nəticə |
|---|---|---|
| Tam SQLite Pest | `composer test` | PASS — 247/247 test, 1861 assertion |
| Tam Herd MySQL Pest | `composer test:mysql` | PASS — 247/247 test, 1861 assertion |
| Unit | `composer test:unit` | PASS — 21/21 test, 76 assertion |
| Architecture | `composer test:architecture` | PASS — 24/24 test, 89 assertion |
| Static/security/route | `composer test:static` | PASS — 27/27 test, 138 assertion |
| PHP format | `composer format:check` | PASS |
| Production asset | `npm run build` | PASS — Vite 8.2.1 |
| Browser inventory | `npm run e2e:list` | PASS — 10 journey × desktop/mobile = 20 |
| Real browser | `npm run e2e` | PASS — 20/20 Chromium execution |
| Composer metadata | `composer validate --no-check-publish --no-interaction` | PASS |
| Composer lock | `composer install --dry-run --no-interaction` | PASS — dəyişiklik yoxdur |
| npm lock | `npm ci --ignore-scripts --dry-run` | PASS — up to date |
| API route manifest | `php artisan route:list --path=api/v1 --json` | PASS — dəqiq 46 named route |
| Herd health | `/up`, `/login` | PASS — HTTP 200/200 |

SQLite və MySQL run-ları bütün root/modul migration-larının fresh qurulmasını və tam rollback ardıcıllığını da yoxlayır. MySQL run yalnız `.env.testing`-dəki prefiksli dedicated credential-ları proses daxilində oxuyub; dəyərlər output və sənəddə göstərilməyib.

## Browser acceptance əhatəsi

Hər journey desktop və mobile-da icra olunub:

1. login/logout və unauthorized redirect;
2. admin user create/suspend və login denial;
3. project create/activate/member idarəsi;
4. Bug report, assignment və assignee workflow;
5. backlog reorder və board drag/drop persistence;
6. label/filter URL state və responsive task list;
7. watch/comment/notification;
8. multi-media upload, image/PDF preview, private download və delete;
9. completed/archived read-only UI;
10. mobile navigation, keyboard/modal focus və JS-disabled core form.

Mobil naviqasiya üçün toggle handler hazır olana qədər button native `disabled` saxlanılır, panel scrollable-dır və E2E panelin açıq/viewport vəziyyətini klikdən əvvəl təsdiqləyir. Bu, ilk render zamanı itən klik yarışını bağlayır.

## API və təhlükəsizlik acceptance-i

Runtime və [`API.md`](API.md) cədvəli metod/yol/ability üzrə exact müqayisə olunub. Feature/security suite-lər active/suspended/outsider/removed-member actor-ları, ability+policy, wrong-parent və cross-project ID-ləri, state/version conflict-ləri, rate limit, CSRF, private field absence, media spoof/compensation və opaque 500 davranışını əhatə edir.

Əlavə process-local HTTP smoke token issuance üçün 201, `/me`, Projects, Tasks, Activity və beş Dashboard read-i üçün JSON 200, current-token revoke üçün body-siz 204, token reuse üçün 401 qaytarıb. Disposable token output və fayla yazılmayıb.

## 2026-09-07 build identikliyi

- `composer.lock` SHA-256: `C77679EC23D9B7559BFFBDBCD33C8DC3AC837976AD17CE24DBCC960AC940F19B`
- `package-lock.json` SHA-256: `BD662466D7C95629FC5FBDB47034DD48615348EEF72756D2963FA09E8568563F`
- `public/build/manifest.json` SHA-256: `E05169A1478C32DC4820A3CDB0E923AD405EC57F3DF40E3D89E8DE7D9896E576`
- `phpunit-mysql.xml` SHA-256: `3BB1B22E22E2D250F531D03325BF47C7F8BFE0114B58EC30B1C8B83CA54519D1`

Bu hash-lər həmin tarixi build-ə aiddir; sonrakı test-discovery və R1 dəyişikliklərindən sonra cari fayl hash-i kimi istifadə edilməməlidir.

## Qəbul edilmiş məhdudiyyətlər

- `phpunit.mysql.xml` exact adı Norton filesystem filter tərəfindən access-denied ilə bloklanıb; funksional və testlə qorunan sabit ad `phpunit-mysql.xml`-dır.
- Windows native Vite binary-si sandbox prosesində `spawn EPERM` verə bilər; eyni locked dependency normal Herd/Windows prosesində production build-i uğurla tamamlayıb.
- Playwright testləri rate-limit davranışını dəyişməmək üçün bir worker ilə işləyir; suite keçib, lakin parallel deyil.
- 2026-09-07 tam baseline-a roadmap maddələri daxil deyildi. 2026-10-08-də əlavə edilmiş məhdud R1 praktikası ayrıca yuxarıda göstərilib; production loose-coupling refaktoru kimi qəbul edilmir.

## 2026-09-07 qəbul qərarı

Roadmap-xarici funksional, arxitektura, security, data, API, build və responsive browser qəbul meyarları yaşıl olduğuna görə baseline qəbul edilib. Production deployment ayrıca infrastructure credential, backup və deploy proseduru tələb edir; lokal acceptance avtomatik production deploy demək deyil.
