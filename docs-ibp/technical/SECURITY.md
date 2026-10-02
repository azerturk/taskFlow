# Təhlükəsizlik bazası

## Qorunan aktivlər

- credential, password hash, session, CSRF və personal access token;
- layihə üzvlüyü və actor üçün görünməyən record metadata-sı;
- comment, Activity və notification payload-ları;
- şəxsi media binary-si, storage path-i və checksum-u;
- project key/sequence, assignment, label, watcher, parent və rank bütövlüyü.

## Əsas təhdidlər

- ID dəyişməklə project/task/comment/media/user/label üzrə IDOR;
- Sanctum ability-ni tam authorization saymaq;
- filter option, activity və nested resource vasitəsilə existence leak;
- suspended və ya project-dən çıxarılmış actor-un access saxlaması;
- completed/archived layihəyə başqa giriş nöqtəsindən mutasiya;
- MIME spoofing, path traversal, header injection, zərərli upload və public URL;
- description/comment/filename/activity üzərindən XSS;
- Web mutation-da CSRF;
- log, Activity, API, DOM və storage-da secret/token/path sızması;
- mass assignment, raw sort/filter SQL və race condition;
- generic exception mesajının istifadəçiyə çıxması.

## Authentication və hesab təhlükəsizliyi

- Web session login normalized e-poçt + IP üzrə 5/dəqiqə limitlidir və uğurda session regenerate edir.
- Logout session-u invalidate, CSRF token-i regenerate edir.
- Login Form Request yalnız input shape yoxlayır; credential attempt və session orchestration `AuthenticationService`-dədir.
- Public registration route-u yoxdur.
- Suspended actor login/token ala bilməz; `EnsureActiveUser` cari authenticated actor-u fail-closed yoxlayır və əlavə unconditional `fresh()` query etmir. Middleware ilkin qorunan route-un Livewire snapshot-ından real update endpoint-ə persistent tətbiq olunur; stale snapshot account-status yoxlamasını bypass etmir.
- Son aktiv admin suspend/demote edilmir.
- Parol heç vaxt loglanmır; hash olunur.
- Admin reset target-in bütün session/PAT-lərini, self-change digər session-ları və bütün PAT-ləri ləğv edir.

## Sanctum token-ləri

- Plaintext token yalnız issuance cavabında bir dəfə görünür; DB-də hash saxlanır.
- Token, hash və Authorization header Resource, Activity, notification, exception, log və browser storage-a yazılmır.
- Ability yalnız canonical allowlist-dən qəbul edilir.
- Protected API route authentication, active-user, ability, Spatie permission və Policy/Gate qatlarından keçir.
- Revoke yalnız cari request token-ini dərhal etibarsız edir.

## Authorization və data isolation

- Admin bütün project/work item record-larını görə bilir.
- Adi actor yalnız üzv olduğu layihəni və həmin layihənin bütün işlərini görür.
- Assignment browse access vermir və almır.
- Mutation project role, reporter/assignee/author/uploader əlaqəsi və project/account statusundan asılıdır.
- List, aggregate və filter-option query-si actor-visible scope-dan başlayır.
- Route binding project/task üçün actor-visible, nested child üçün parent-scoped işləyir.
- Missing, hidden, soft-deleted, removed-membership və wrong-parent record eyni `resource_not_found` 404 cavabını verir.
- Ability denial record binding-dən əvvəl 403 verir və record existence göstərmir.
- Completed/Archived state Web, API, Livewire və direct service çağırışında mutasiyanı bloklayır.
- Köhnə Livewire snapshot yeni səlahiyyət vermir: account statusu hər update request-də, project/task visibility isə component query/policy/service sərhədində yenidən qiymətləndirilir.

## Input və output

- HTTP input Form Request, Livewire input ekvivalent validation istifadə edir.
- DTO yalnız validated input-dan qurulur; `request()->all()` domain flow-a ötürülmür.
- Enum, sort direction/field və filter allowlist-dir; `per_page` 1–100.
- Form Request Eloquent existence/uniqueness query-si və domain qərarı vermir.
- Blade user mətnini escape edir; plain-text newline raw HTML kimi render olunmur.
- API Resource sahələri explicit-dir və raw model serialize etmir.
- Resource/Blade/view composer query və lazy loading etmir.
- Reporter, status, rank, version, issue sequence/nömrə kimi server sahələri request-shaped mass assignment-dan gəlmir.

## Media təhlükəsizliyi

- Storage private-dir; authorized stream olmadan URL yoxdur.
- Random path, safe filename və server-detected MIME/extension cütü istifadə edilir.
- SVG, HTML, JS, archive, script, executable və unknown binary qadağandır.
- Limit: 5 fayl/request, 10 MB/fayl və təhlükəsiz image dimension/pixel həddi.
- Parent Task əvvəl authorize, association sonra parent daxilində resolve olunur.
- Response `nosniff`, təhlükəsiz disposition və cache policy verir.
- Resource/log disk, path, checksum, temporary path və content göstərmir.
- Batch failure tam compensation, fiziki delete failure retry edilə bilən safe UUID metadata ilə nəticələnir.

## Activity və notification

- Canonical Activity enum və vahid recursive sanitizer istifadə olunur.
- Denylist password, token, secret, authorization, cookie, path və binary açar/dəyərlərini təmizləyir.
- Payload depth, item count və string ölçüsü limitlidir; yalnız safe scalar/enum/date və təsdiqlənmiş old/new sahələr saxlanır.
- Notification safe ID və summary saxlayır; linked səhifə açılarkən yenidən authorize edilir.
- Recipient query-ləri actor/member state-i yoxlayır, actor öz action bildirişini almır.

## Rate limit-lər

| Limit | Dəyər | Açar |
|---|---:|---|
| Web login | 5/dəqiqə | normalized email + IP |
| API token issuance | 5/dəqiqə | normalized email + IP |
| Adi API | 120/dəqiqə | actor + IP |
| Media upload | 10/dəqiqə | actor + IP |
| Project/task/backlog/board search | 30/dəqiqə | actor + IP |

Detail read və media stream search/upload xüsusi bucket-lərini istehlak etmir.

## Error müqaviləsi

- 401 — unauthenticated, invalid/revoked token və ya suspended actor;
- 403 — ability, permission və ya policy denial;
- 404 — missing və təhlükəsiz parent/scope mismatch;
- 409 — sənədləşdirilmiş state/concurrency conflict;
- 422 — input və məqsədli domain validation;
- 429 — named rate limit;
- 500 — generic və opaque gözlənilməz xəta.

Generic `LogicException`, `ModelNotFoundException`, DB/storage/runtime mesajı heç bir adapter tərəfindən user output-a kopyalanmır.

## Web və DB qoruması

- Web mutation-ları CSRF middleware-dən keçir; hidden button authorization sayılmır.
- Environment cookie `secure`, `httpOnly`, `sameSite` parametrlərini deploy kontekstində idarə edir.
- Unique/FK/index constraint-lər service invariant-larını race altında tamamlayır.
- Issue allocation və rank write transaction/row lock istifadə edir.
- SQLite və MySQL fresh/rollback test-ləri migration bütövlüyünü yoxlayır.

## Roadmap sərhədi

2FA, malware quarantine, strict CSP rollout, token expiry/rotation UI, security-event alerting, automated dependency/container/secret scanning, retention/privacy və incident runbook-ları cari implementasiyanın hissəsi deyil; yalnız [`ROADMAP.md`](../../ROADMAP.md) daxilindədir.
