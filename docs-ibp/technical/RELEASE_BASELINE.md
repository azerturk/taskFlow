# Release baseline

## Qəbul statusu

2026-09-07 (Asia/Baku) tarixində roadmap-xarici TaskFlow implementasiyası Windows + Laravel Herd qəbul mühitində tam avtomatlaşdırılmış gate-lərdən keçib. Açıq implementation task/status sənədi saxlanılmır; bu fayl cari yoxlanmış baseline sübutudur.

## Runtime

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

Layihənin minimum müqaviləsi PHP 8.3+-dır; qəbul run-u PHP 8.4.24-də aparılıb. Unix/Linux ayrıca gate deyil.

## Yekun test nəticələri

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

## Build identikliyi

- `composer.lock` SHA-256: `C77679EC23D9B7559BFFBDBCD33C8DC3AC837976AD17CE24DBCC960AC940F19B`
- `package-lock.json` SHA-256: `BD662466D7C95629FC5FBDB47034DD48615348EEF72756D2963FA09E8568563F`
- `public/build/manifest.json` SHA-256: `E05169A1478C32DC4820A3CDB0E923AD405EC57F3DF40E3D89E8DE7D9896E576`
- `phpunit-mysql.xml` SHA-256: `3BB1B22E22E2D250F531D03325BF47C7F8BFE0114B58EC30B1C8B83CA54519D1`

## Qəbul edilmiş məhdudiyyətlər

- `phpunit.mysql.xml` exact adı Norton filesystem filter tərəfindən access-denied ilə bloklanıb; funksional və testlə qorunan sabit ad `phpunit-mysql.xml`-dır.
- Windows native Vite binary-si sandbox prosesində `spawn EPERM` verə bilər; eyni locked dependency normal Herd/Windows prosesində production build-i uğurla tamamlayıb.
- Playwright testləri rate-limit davranışını dəyişməmək üçün bir worker ilə işləyir; suite keçib, lakin parallel deyil.
- Roadmap maddələrinin heç biri bu baseline-a daxil deyil.

## Release qərarı

Roadmap-xarici funksional, arxitektura, security, data, API, build və responsive browser qəbul meyarları yaşıl olduğuna görə baseline qəbul edilib. Production deployment ayrıca infrastructure credential, backup və deploy proseduru tələb edir; lokal acceptance avtomatik production deploy demək deyil.
