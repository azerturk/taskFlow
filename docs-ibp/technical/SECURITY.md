# Təhlükəsizlik bazası

## Sadə dillə: təhlükəsizlik yalnız login deyil

Murad login olubsa sistem onun kim olduğunu bilir. Amma bu, Muradın istənilən project/task/faylı aça bilməsi demək deyil. Hər request-də görünürlük və əməliyyat icazəsi ayrıca yoxlanır.

Məsələn, URL-də comment ID-sini dəyişib başqa task-ın şərhinə çatmaq cəhdi **IDOR** riskidir: user ID ilə obyekt seçir, amma server həmin obyektin ona və parent-ə uyğunluğunu yoxlamalıdır. Parent-scoped binding yanlış parent altında gələn ID-ni də safe 404 edir.

**XSS** user mətninin browser-də kod kimi işləməsi riskidir; plain text/escape buna qarşı sərhəddir. **CSRF** isə login olmuş browser adından istənməyən mutation göndərilməsi riskidir; Web CSRF middleware-i fərqli problemi qoruyur. Biri o birini əvəz etmir.

403 «bu əməliyyata icazə yoxdur», safe 404 isə «bu context-də göstərilə bilən resource yoxdur» deməkdir. 404 ilə gizli record-un həqiqətən mövcud olub-olmadığını açıqlamamağa çalışırıq. 500 zamanı database/storage daxili mesajını göstərmək də əlavə məlumat sızdıra bilər.

Addım-addım dərslər: [authorization](../diagrams/flows/authorization.md), [nested 404 və 403](../extended/nested-resources-and-safe-404.md), [error statusları](../extended/http-errors.md), [private media](../diagrams/flows/media-stream.md), [audit payload-u](../diagrams/flows/activity.md). Aşağıdakı risk siyahısı bu nümunələrin texniki sərhədini saxlayır.

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
- Login Form Request yalnız input formasını yoxlayır; credential attempt və session idarəsi `AuthenticationService`-dədir. Rate limit `AppServiceProvider` named limiter-i və `routes/web.php` throttle middleware-i ilə tətbiq olunur, service daxilində deyil.
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
- Completed/Archived vəziyyəti detail/member/iş/əməkdaşlıq mutasiyalarını service səviyyəsində də bloklayır. Bu, bütün lifecycle çağırışlarının qadağan olması deyil: `completed -> active|archived` icazəlidir, archived terminaldır. Draft detail/member hazırlığına icazə verir, iş/əməkdaşlıq mutasiyasına yox.
- Administratorun account-suspend cleanup-ı adi project mutation-u deyil: açıq assignment və watcher-lər layihə lifecycle-ından asılı olmayaraq ləğv olunur. Bu istisna `AdminUserService::suspend()` və suspension repository metodları ilə məhduddur.
- Köhnə Livewire snapshot yeni səlahiyyət vermir: account statusu hər update request-də, project/task visibility isə component query/policy/service sərhədində yenidən qiymətləndirilir.

## Input və output

- HTTP input Form Request, Livewire input uyğun validation istifadə edir. Cari istisna olaraq project key və admin user email unikallığı Form Request-in Laravel presence rule-u ilə DB-də yoxlanır; bunu pure input-shape yoxlaması kimi təsvir etmək olmaz.
- DTO yalnız validated input-dan qurulur; `request()->all()` domain flow-a ötürülmür.
- Enum, sort direction/field və filter allowlist-dir; `per_page` 1–100.
- Form Request birbaşa Eloquent workflow və authorization qərarı vermir. Hazırda `StoreProjectRequest`/`UpdateProjectRequest` `Rule::unique('projects', 'key')`, `StoreAdminUserRequest`/`UpdateAdminUserRequest` isə `users.email` uniqueness qaydası istifadə edir. DB UNIQUE constraint-i race zamanı son qoruma olaraq qalır. Digər membership/parent/assignee/label invariantlarının sahibi service-dir.
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
- Batch failure DB rollback və bütün saxlanmış faylların cleanup cəhdi ilə nəticələnir; fiziki delete failure-da retry edilə bilən safe UUID metadata saxlamaq cəhd edilir.

Kompensasiya da xarici disk və DB-dən asılı əməliyyatdır. Fiziki cleanup alınmasa `MediaCleanupPendingException`, hətta cleanup record-u da yazıla bilməsə təhlükəsiz critical log yaranır. «Failure zamanı fayl mütləq artıq yoxdur» zəmanəti verilmir. Attachment əlaqəsi əvvəl commit edildiyinə görə sonrakı cleanup xətası həmin əlaqəni geri qaytarmır. Avtomatik retry worker-i və cleanup idarəetmə UI-sı cari kodda yoxdur; detallı mərhələlər [TRANSACTIONS_AND_FAILURES.md](TRANSACTIONS_AND_FAILURES.md) daxilindədir.

## Activity və notification

- Canonical Activity enum və vahid recursive sanitizer istifadə olunur.
- Denylist password, token, secret, authorization, cookie, path və digər həssas hissələri daşıyan **açarları** silir; etibarsız UTF-8/control-byte mətn və icazəsiz obyekt dəyərlərini də qəbul etmir. Bu, adi `title` kimi açarın altına qoyulmuş istənilən secret-i tanıyan mətn skaneri deyil; caller yalnız təsdiqlənmiş safe sahələri ötürməlidir.
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
- Project key lock-u hazırda soft-delete olunmamış issue mövcudluğuna əsaslanır; bütün issue-lər soft-delete olunarsa key dəyişməsi yenidən açılır. Bu, tarixi identity üzərində daimi lock deyil ([Projects məhdudiyyəti](../modules/PROJECTS.md)).
- SQLite və MySQL fresh/rollback test-ləri migration bütövlüyünü yoxlayır.

## Roadmap sərhədi

2FA, malware quarantine, strict CSP rollout, token expiry/rotation UI, security-event alerting, automated dependency/container/secret scanning, retention/privacy və incident runbook-ları cari implementasiyanın hissəsi deyil; yalnız [`ROADMAP.md`](../../ROADMAP.md) daxilindədir.

## R1 tədris modullarının təhlükəsizlik sərhədi

Learning modulları HTTP/UI, user relation, session/PAT, storage və məhsul cədvəllərinə çıxış təqdim etmir. `PublishLearningEntryData` title-ı özü yoxlayır, çünki entry point HTTP Form Request deyil. Bu service user-facing authorization API-si sayılmır. Gələcəkdə HTTP giriş əlavə etmək ayrıca authentication/policy/input dizaynı tələb edər.

`R1LearningBoundaryTest` və `LearningBoundaryGuard` qadağan import, cədvəl və qat istifadəsini statik fixture-lərlə yoxlayır. Bu, təhlükəsizlik sandbox-u və ya bütün dinamik PHP çağırışlarının formal sübutu deyil. Event payload-u sadə ID/title/immutable tarixdən ibarətdir; credential və private path daşımır. Queue/outbox olmadığı üçün commit-dən sonra proses dayanması event-i itirə bilər; after-commit bunu dayanıqlı delivery-yə çevirmir.
