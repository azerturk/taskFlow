# TaskFlow — junior hesabatlarının birləşdirilmiş texniki analizi

**Tarix:** 2026-10-01  
**Analiz hədəfi:** hazırkı `taskFlow-main` checkout-u  
**Məqsəd:** junior hesabatlarındakı tapıntıları cari kodla yoxlamaq, eyni mövzuları birləşdirmək və bu branch-də qalan real işləri müəyyənləşdirmək.

## 1. Scope və yoxlama üsulu

Bu sənəd hazırlanarkən `report-latest` daxilindəki bütün 10 mənbə oxunub:

- `TaskFlow_Report-Abdulla.docx`
- `TaskFlow_Report-Farhad.docx`
- `TaskFlow.Report-Fidan.docx`
- `TaskFlow.Report-Fardi.docx`
- `Taskflow-report-Abdulla-1.md`
- `Taskflow-report-Ahmad-1.md`
- `Taskflow-Report-Ahmad.md`
- `Taskflow-report-Fardi-1.md`
- `Taskflow-report-Farhad-1.md`
- `Taskflow-report-Fidan-1.md`

Hesabat iddiaları aşağıdakılarla tutuşdurulub:

- kök `AGENTS.md` və bütün əlaqəli `docs-ibp` business/technical/module müqavilələri;
- cari Web/API route-ları, Blade view-ları, Livewire komponentləri, middleware-lər, service/repository/policy qatları;
- mövcud Pest və Playwright test mənbələri;
- Livewire-ın quraşdırılmış vendor kodundakı update-route və persistent-middleware davranışı;
- cari checkout-da `composer test` icrası.

Juniorun öz hesabatında “FIXED” yazması həmin düzəlişin onun şəxsi branch-ində olması ilə uyğun ola bilər. Bu analiz həmin branch-lərə çıxış etmədiyi üçün onların işini təkzib etmir. Burada status yalnız **hazırkı checkout-da kod və test mövcuddurmu?** sualına əsasən verilir. Junior branch-ində olan, amma bu checkout-da olmayan düzəliş aşağıda “cari branch-də açıqdır” kimi göstərilir.

## 2. Yekun nəticə

Hazırkı branch-də dörd real düzəliş sahəsi təsdiqlənir:

| Prioritet | Mövzu | Cari status | Hesabatlarda təkrarlanma |
|---|---|---|---|
| P0 | Suspend edilmiş istifadəçi üçün Livewire persistent active-user sərhədi | Açıq | Ahmad, Abdulla, Fidan, Farhad və Fardi materiallarında |
| P1 | Project Key input-un görünən dəyəri ilə real browser validasiyasının ziddiyyəti | Açıq | Ahmad, Abdulla, Farhad və Fardi; Fidanın `PAY-42` qeydi yaxın, amma fərqli anlayış problemidir |
| P1 | Task detail-də Watch/Unwatch və watcher siyahısının olmaması | Açıq UI/contract boşluğu | Ahmad, Abdulla, Farhad və Fardi materiallarında |
| P2 | Livewire status dəyişəndən sonra header badge və Recent activity-nin köhnə qalması | Açıq UI sinxronizasiya problemi | Abdulla və Fardi yekun hesabatlarında |

Əlavə olaraq bir tək-developer UX tapıntısı təsdiqlənir:

- Project label idarəetmə səhifəsi və route-u var, amma əsas UI-da ona keçid yoxdur.

Aşağıdakılar hazırkı məhsul müqaviləsinə görə bug deyil:

- `PAY-42` dəyərinin Project Key kimi qəbul edilməməsi;
- notification-dan task-ı açmağın notification-u avtomatik read etməməsi;
- unauthenticated “Forgot password” axınının olmaması — bu, cari scope-da nəzərdə tutulmayıb, amma operational recovery qərarı tələb edən məhsul riskidir;
- Playwright Chromium quraşdırılmasında lokal CA/TLS problemi — tətbiq xətası deyil.

Cari `composer test` nəticəsi **247/247 test, 1861 assertion, PASS**-dır. Bu nəticə `docs-ibp/technical/RELEASE_BASELINE.md` ilə eynidir. Junior hesabatlarında görünən 250/253/258 sayları onların ayrı branch və ya ayrı kod vəziyyətlərinə aid ola bilər; həmin saylar bu checkout üçün qəbul sübutu deyil.

## 3. Təkrarlanan və cari branch-də açıq problemlər

### 3.1. P0 — `EnsureActiveUser` Livewire update request-lərində persistent deyil

**Mənbələr:** Ahmad, Abdulla, Fidan, Farhad və Fardi hesabatları. Bir neçə yekun hesabat bunu şəxsi branch-də düzəldilmiş kimi təqdim edir.

**Cari branch-də təsdiqlənmiş vəziyyət**

- Normal qorunan Web route-lar `auth` və `active-user` middleware-lərindən keçir: məsələn, `Modules/Tasks/routes/web.php`.
- Livewire-ın faktiki update route-u `livewire-92be643f/update` yolunda yalnız `web` və `RequireLivewireHeaders` middleware-ləri ilə qeydiyyatdadır.
- `app/Providers/AppServiceProvider.php:43`-də `Livewire::addPersistentMiddleware(...)` çağırışı yoxdur; faylda `Livewire` və `EnsureActiveUser` import-u da yoxdur.
- Quraşdırılmış Livewire kodunun `vendor/livewire/livewire/src/Mechanisms/PersistentMiddleware/PersistentMiddleware.php` siyahısında framework authentication/authorization middleware-ləri var, amma `App\Http\Middleware\EnsureActiveUser` yoxdur.
- Cari test ağacında hesabatlarda adı çəkilən `LivewirePersistentActiveUserTest.php` və ya `LivewireActiveUserBoundaryTest.php` mövcud deyil.

**Real səbəb**

Livewire component ilkin olaraq `/dashboard` və ya `/tasks/{task}` kimi qorunan route daxilində mount olunur. Sonrakı interaction isə həmin route-a deyil, ümumi Livewire update endpoint-inə gedir. Livewire yalnız persistent allowlist-də olan ilkin route middleware-lərini snapshot-dan yenidən tətbiq edir. Custom `active-user` bu siyahıda olmadığı üçün köhnə session hələ autentifikasiya verirsə, `EnsureActiveUser` update request-dən əvvəl işləməyə bilər.

Bu, yalnız nəzəri sərhəd deyil. `QuickTaskCreate::render()` `QuickTaskCreateService::projectsFor()` çağırır, `EloquentProjectRepository::activeForTaskCreation()` isə actor-un `status` sahəsini ayrıca yoxlamadan görünən layihələri query edir. Deməli müdafiəni yalnız hər component-in daxili policy çağırışına buraxmaq vahid fail-closed account sərhədi vermir.

Default session driver `database`-dir və `AdminUserService::suspend()` session qeydlərini silir. Bu, əsas müdafiələrdən biridir, lakin `EnsureActiveUser`-ın hər request-də işləməsi yenə müqavilə tələbidir: başqa session driver-i, silinməyən/stale cookie və Livewire snapshot ayrıca sərhəddir.

**Düzgün yanaşma**

Account statusu component-lərdə ayrı-ayrı yoxlanmamalıdır. `EnsureActiveUser` normal Web/API ilə yanaşı Livewire update lifecycle-a da mərkəzləşdirilmiş persistent middleware kimi daxil edilməlidir. Component policy-ləri isə record/project authorization-u qorumağa davam etməlidir.

**Praktik düzəliş təklifi**

1. Junior branch-lərindəki uyğun diff tapılıb review edilsin; yoxdursa `AppServiceProvider::boot()` daxilində `EnsureActiveUser` Livewire persistent middleware kimi qeydiyyata alınsın.
2. Vendor koduna dəyişiklik edilməsin.
3. Regression testi saxta `Livewire::test()` ilə məhdudlaşmasın. Vendor kodu persistent middleware-i yalnız real Livewire update route-da tətbiq etdiyinə görə test ilkin page snapshot-u almalı, actor-u suspend etməli və real update HTTP request-i göndərməlidir.
4. Ən azı bu hallar yoxlanmalıdır:
   - active user update edə bilir;
   - suspend edilmiş actor 401/fail-closed cavab alır və response protected project/task məlumatı daşımır;
   - project membership-i silinmiş actor köhnə snapshot-la həmin project məlumatını ala bilmir;
   - normal Web və API active-user davranışı dəyişmir.
5. `docs-ibp/technical/SECURITY.md` və `docs-ibp/modules/HOST_APPLICATION.md` faktiki davranışla birlikdə yenilənsin.

**Qəbul meyarı**

- Real Livewire HTTP regression testi əvvəlki kodda fail, düzəlişdən sonra pass etməlidir.
- SQLite və dedicated MySQL suite-ləri pass etməlidir.
- Suspend edilmiş actor üçün response body-də project/task adı, ID-si və option siyahısı olmamalıdır.

### 3.2. P1 — Project Key formunun browser validasiyası backend normalizasiyası ilə ziddir

**Mənbələr:** Ahmad, Abdulla, Farhad və Fardi hesabatlarında eyni problem təkrarlanır. Fidanın `PAY-42` müşahidəsi ayrıca termin qarışıqlığıdır və aşağıda izah olunur.

**Cari branch-də təsdiqlənmiş vəziyyət**

- `Modules/Projects/resources/views/_form.blade.php:18` input-u `pattern="[A-Z][A-Z0-9]{1,9}"` və Tailwind `uppercase` class-ı ilə render edir.
- CSS `uppercase` yalnız görünüşü dəyişir; input-un real `value` dəyərini dəyişmir.
- Browser HTML pattern-i case-sensitive yoxladığı üçün `pay` real value-si serverə göndərilməzdən əvvəl rədd olunur.
- Backend isə `Modules/Projects/app/Http/Requests/StoreProjectRequest.php:10-12` daxilində `trim` + `strtoupper` edir və sonra uppercase regex tətbiq edir.
- `ProjectLifecycleAndKeyTest.php:27-34` backend-ə birbaşa `' pay '` göndərib `PAY` saxlandığını yoxlayır; browser-in native constraint validation mərhələsini yoxlamır.
- `tests/e2e/taskflow.spec.js:84` və `:269` yalnız əvvəlcədən uppercase `DSK/MOB/NJD/NJM` daxil edir. Lowercase browser regression-u yoxdur.
- `resources/js/app.js` daxilində Project Key üçün real-value normalizasiya kodu yoxdur.

**Real səbəb**

UI və backend fərqli giriş müqaviləsi tətbiq edir. Backend lowercase-i canonical uppercase-ə çevirir, amma HTML pattern backend-ə çatmazdan əvvəl lowercase-i bloklayır. Vizual `uppercase` istifadəçiyə real value-nun dəyişdiyi barədə yanlış siqnal verir.

**Düzgün yanaşma**

Server canonical formatın sahibidir və `strtoupper` saxlanmalıdır. Browser formu isə serverin qəbul etdiyi xam girişlə uyğun olmalıdır. JavaScript UX yaxşılaşdırması ola bilər, amma correctness JavaScript-dən asılı qalmamalıdır.

**Praktik düzəliş təklifi**

1. Junior branch-lərindəki uyğun fix review edilərək cari branch-ə inteqrasiya edilsin.
2. HTML pattern lowercase/mixed-case xam girişi qəbul etsin, məsələn `[A-Za-z][A-Za-z0-9]{1,9}`.
3. Real value dəyişmirsə vizual `uppercase` class-ı çıxarılsın. Alternativ olaraq JavaScript aktiv olanda input event-i real `value`-nu uppercase etsin; buna baxmayaraq JavaScript-siz pattern lowercase-i qəbul etməlidir.
4. Helper text Project Key (`PAY`) ilə generated issue/display key (`PAY-42`) fərqini izah etsin.
5. Aşağıdakı regression-lar əlavə edilsin:
   - Web/browser: `mqa` submit olunur və `MQA` saxlanır;
   - JavaScript disabled: lowercase yenə submit olunur və uppercase saxlanır;
   - `PAY`, mixed-case, duplicate və `PAY-42` halları;
   - API/backend canonical normalization ayrıca saxlanır.

**Qəbul meyarı**

- İstifadəçinin gördüyü dəyərlə formun faktiki value-si zidd olmamalıdır.
- `pay` həm JS aktiv, həm də deaktiv halda uğurla yaradılmalı və DB-də `PAY` olmalıdır.
- `PAY-42` Project Key kimi rədd edilməlidir.

### 3.3. P1 — Watcher backend-də var, amma task detail UI-da əlçatan deyil

**Mənbələr:** Ahmad, Abdulla, Farhad və Fardi materiallarında təkrarlanır.

**Cari branch-də təsdiqlənmiş vəziyyət**

- Web mutation route-ları mövcuddur: `Modules/Tasks/routes/web.php:26-27`.
- API list/add/remove route-ları mövcuddur: `Modules/Tasks/routes/api.php:18,37-38`.
- `TaskWatcherController`, `TaskWatcherService` və `EloquentTaskWatcherRepository` self-watch, manager-managed watcher, membership və activity qaydalarını implementasiya edir.
- `TaskWatcherNotificationTest.php` route/service davranışını test edir.
- `Modules/Tasks/resources/views/show.blade.php` daxilində watcher siyahısı, Watch/Unwatch düyməsi və manager üçün watcher idarəsi yoxdur.
- `TaskQueryService::detailPage()` yalnız task, memberships, activities və `canViewActivity` qaytarır; watcher presentation məlumatı hazırlamır.
- `docs-ibp/modules/TASKS.md:31` Web səthində watcher əməliyyatlarını göstərir. Buna görə backend-in mövcud olması yetərli deyil; əsas UI axını yarımçıqdır.

**Real səbəb**

Use case və route-lar yaradılıb, amma read model və Blade presentation həmin capability-ni istifadəçiyə çıxarmır. URL-i və request formasını əl ilə bilən istifadəçi əməliyyatı edə bilər, adi istifadəçi UI-dan edə bilmir.

**Düzgün yanaşma**

Controller-ə repository əlavə edilməməlidir. Mövcud `TaskQueryService::detailPage()` watcher siyahısı, cari actor-un `is_watching` vəziyyəti və manager üçün uyğun üzv seçimlərini presentation-ready formada hazırlamalıdır. Mutation yenə `TaskWatcherController -> TaskWatcherService` sərhədindən keçməlidir.

**Praktik düzəliş təklifi**

1. `TaskQueryService`/repository read sərhədində watcher-ləri eager-load və ya purpose-specific query ilə hazırla.
2. Task detail-də:
   - cari actor üçün Watch və ya Unwatch control-u;
   - watcher siyahısı;
   - yalnız manager üçün başqa aktiv project member-i əlavə/silmə control-u göstər.
3. Completed/archived project-də mutation control-ları göstərmə; read-only watcher siyahısı məhsul qərarına uyğun qala bilər.
4. Web feature testlərində member self-watch/unwatch, manager target idarəsi, outsider və completed/archived halları yoxlanılsın.
5. Playwright journey 7 real UI control-u istifadə etsin; birbaşa route request-i ilə kifayətlənməsin.

**Qəbul meyarı**

- Project üzvü task detail-dən özünü watch/unwatch edə bilir.
- Manager başqa aktiv üzvün watcher vəziyyətini idarə edə bilir.
- Adi member başqa user-i idarə edə bilmir.
- UI əməliyyatı Activity və notification qaydalarını pozmur.

### 3.4. P2 — Livewire status dəyişikliyi səhifənin digər hissələrini yeniləmir

**Mənbələr:** Abdulla və Fardi yekun hesabatları.

**Cari branch-də təsdiqlənmiş vəziyyət**

- `Modules/Tasks/resources/views/show.blade.php:10` header status badge-i serverdən ilkin gəlmiş `$task->status` ilə statik render edir.
- Eyni faylın `:31` sətrində `TaskStatusSelector` ayrıca Livewire component-dir.
- `:47` sətrində Recent activity ayrıca statik Blade blokudur.
- `TaskStatusSelector::change()` task-ı dəyişir, yalnız component-in `expectedVersion`, `status` və `success` state-ni yeniləyir; redirect və ya parent səhifə refresh event-i yoxdur.
- Repository/service düzgün olaraq yeni statusu, version-u və Activity-ni yazır. Problem persistence deyil, presentation sinxronizasiyasıdır.

**Real səbəb**

Livewire yalnız öz component DOM sərhədini morph edir. Header badge və Recent activity həmin component-in xaricində olduğuna görə yeni status və activity-ni avtomatik almır.

**Düzgün yanaşma**

Səhifədə eyni domain state-in birdən çox görünüşü varsa mutation-dan sonra hamısı eyni canonical state-ə gətirilməlidir. Junior səviyyəsi və mövcud server-rendered arxitektura üçün ən təhlükəsiz həll uğurlu status dəyişməsindən sonra task detail-ə redirect/full refresh-dir. Tam inline UX saxlanılacaqsa, status badge və activity üçün məqsədli event/read refresh dizaynı tələb olunur.

**Praktik düzəliş təklifi**

1. Sadə variant: Livewire `change()` uğurundan sonra `tasks.show` route-una redirect et; səhifə status, version, available transitions və activity-ni yenidən service-lərdən alır.
2. Inline variant seçilərsə browser event yalnız badge text-ni dəyişməklə kifayətlənməsin; Recent activity və bütün status-dependent control-lar da refresh edilməlidir.
3. Feature/Livewire testindən əlavə browser testi status dəyişikliyindən sonra header badge və Activity event-in eyni səhifədə yeni olduğunu yoxlasın.

**Qəbul meyarı**

- Mutation-dan dərhal sonra selector, header badge və Recent activity eyni statusu göstərir.
- Refresh etmədən köhnə badge/activity qalmır.
- `expected_version` növbəti mutation üçün yenilənmiş qalır.

## 4. Yalnız bir developerdə rast gəlinən təsdiqlənmiş müşahidələr

### 4.1. Ahmad — Project label səhifəsinə görünən keçid yoxdur

**Cari sübut**

- Label səhifəsi və route-u mövcuddur: `Modules/Tasks/resources/views/labels/index.blade.php` və `Modules/Tasks/routes/web.php:34-37`.
- `ProjectPolicy::manageLabels()` yalnız active project manager üçün icazə verir.
- `Modules/Projects/resources/views/show.blade.php` Edit, lifecycle, Manage members, View tasks və Create task control-ları göstərir, amma `projects.labels.index` linki yoxdur.
- Tasks index də yalnız `TaskFilters` render edir və label management linki daşımır.

**Qiymət:** backend bug deyil; manager üçün real discoverability boşluğudur.

**Praktik düzəliş:** active project detail action-larına `@can('manageLabels', $project)` ilə “Manage labels” linki əlavə et. Manager/member və active/completed/archived visibility testləri yaz.

### 4.2. Ahmad — son admin üçün unauthenticated password recovery yoxdur

**Cari sübut**

- `routes/web.php:29-30` yalnız authenticated self-change təqdim edir və cari parolu tələb edir.
- `routes/web.php:42` adminin başqa user üçün password reset əməliyyatıdır.
- Guest route qrupunda yalnız login GET/POST var; forgot-password route və login səhifəsində recovery linki yoxdur.
- `docs-ibp/modules/HOST_APPLICATION.md` ictimai səthdə bu axını vəd etmir; mail əsas dependency deyil.

**Qiymət:** cari scope-a görə bug deyil. Amma yeganə admin parolunu unutduqda UI-dan recovery olmaması real operational riskdir.

**Praktik qərar:** bunu səssizcə auth feature kimi əlavə etmə. Məhsul sahibi aşağıdakılardan birini ayrıca qəbul etməlidir:

- mail əsaslı self-service recovery-ni yeni scope kimi implementasiya etmək;
- yalnız təhlükəsiz server/CLI runbook saxlamaq;
- ən azı iki aktiv admin üzrə operational qayda müəyyən etmək.

Seçilən variant security sənədi və testlə birlikdə qəbul edilməlidir.

### 4.3. Ahmad — Playwright Chromium CA/TLS problemi

Hesabatda `UNABLE_TO_VERIFY_LEAF_SIGNATURE` lokal environment problemi `NODE_OPTIONS=--use-system-ca` ilə həll olunub. Cari repository kodunda bu problemin tətbiqdən yarandığını göstərən sübut yoxdur. Buna görə application bug və universal setup addımı kimi təqdim edilməməlidir.

Eyni problem Herd/Windows qəbul mühitində təkrarlanarsa, certificate verification-u söndürmək əvəzinə sistem trust store istifadə edən təhlükəsiz həll və yalnız həmin mühit üçün troubleshooting qeydi əlavə edilə bilər.

## 5. Bug olmayan və ya ayrıca məhsul qərarı tələb edən qeydlər

### 5.1. Fidan — `PAY-42` Project Key deyil

`PAY` project key-dir. `PAY-42` isə project key + project-local issue sequence ilə serverin yaratdığı task display key-dir. Bu fərq `docs-ibp/business/GLOSSARY.md`, `BUSINESS_RULES.md` və `TaskDisplayNumberBackfill`/issue allocation axınında sabitdir.

Nəticə: `PAY-42`-nin Project Key inputunda rədd edilməsi düzgün davranışdır. Burada düzəldilməli hissə validation deyil, helper text və concept izahıdır.

### 5.2. Ahmad — “Open task” notification-u avtomatik read etmir

`resources/views/notifications/index.blade.php:3` “Open task” linkini və ayrıca “Mark read” formunu məqsədli olaraq ayırır. `NotificationController` və `NotificationCenterService` yalnız explicit read/read-all mutation-ları təqdim edir. Sənədlər də notification inbox/read əməliyyatını Web səthi kimi göstərir, “open = read” qaydası qoymur.

Nəticə: bug deyil. Məhsul qərarı dəyişərsə GET linkində state mutation etmək əvəzinə authorization-dan keçən məqsədli “mark read and redirect” POST/PATCH flow-u hazırlanmalıdır.

### 5.3. Quraşdırma warning-ləri

Farhadın ilkin hesabatındakı ümumi warning-lər konkret xəta, təkrar addımı və cari failure vermir. İstifadəçi qərarına uyğun olaraq bunlar action item sayılmır.

## 6. Başa düşülməyən mövzular

Bu bölmə juniorların hesabatlarda açıq qeyd etdiyi və ya eyni səhvi izah etmək üçün vacib olan conceptləri cari kod üzərindən izah edir.

### 6.1. `version` və `expected_version` — optimistic concurrency

**Kod nümunəsi:** `TaskStatusSelector.php:20,30,40-42,52`, `TaskStatusService.php:72-74,94`.

- `version` serverdə task record-un cari dəyişiklik nömrəsidir.
- `expected_version` client-in formu/səhifəni açanda gördüyü versiyadır.
- Service task-ı lock edir və `task.version !== expected_version` olarsa `TaskVersionConflict` atır.
- Uğurlu status/rank mutation-u version-u artırır.

Məqsəd iki istifadəçinin köhnə məlumatla bir-birinin dəyişikliklərini səssizcə əzməsinin qarşısını almaqdır. Bu validation format xətası deyil; state/concurrency conflict-dir və 409 kimi map olunur.

### 6.2. Project Key və issue/display key

- Project Key: `PAY`; layihənin 2–10 simvolluq canonical kodudur.
- Issue number: project daxilində atomik ayrılan `42`.
- Display key/Task number: `PAY-42`.

Client display key, issue number və ilkin version seçmir. Bunlar server-owned field-lərdir.

### 6.3. Global role və project-local role

`admin|project_manager|member` qlobal capability qatıdır. `manager|member` isə konkret project daxilində authority-dir. Məsələn, qlobal `member` müəyyən project-də `manager` ola bilər; qlobal `project_manager` isə üzv olmadığı project-i avtomatik idarə etmir.

**Kod nümunəsi:** `TaskPolicy` əvvəl Spatie permission-u, sonra `ProjectMemberService::canManage/isMember` nəticəsini yoxlayır.

### 6.4. Sanctum ability, Policy/Gate və IDOR

`Modules/Tasks/routes/api.php:12,24` ability ilə route ailəsini ayırır. Ability yalnız token-in hansı endpoint sinfinə çata biləcəyini deyir; record icazəsi deyil.

`TasksServiceProvider.php:44-45` task route binding-i `findVisibleOrFail(actor, id)` ilə edir. Comment, attachment və label binding-ləri də parent daxilində resolve olunur. Sonra controller Policy çağırır. Bu qatlar birlikdə IDOR-u bloklayır:

1. token ability;
2. actor-visible route binding;
3. Policy/Gate;
4. service invariantı.

Yalnız `tasks:write` ability-si olan outsider gizli task-ı dəyişə bilməz.

### 6.5. Livewire persistent middleware

İlkin page request middleware-dən keçsə də, Livewire interaction ayrıca update endpoint-ə gedir. Livewire snapshot ilkin route-u saxlayır və yalnız persistent allowlist-dəki middleware-ləri yenidən işlədir. Custom account-status middleware-i ayrıca əlavə olunmasa, “ilk səhifə qorunubsa bütün sonrakı interaction-lar da avtomatik qorunur” fərziyyəsi doğru deyil.

Bu səbəbdən real HTTP Livewire regression testi vacibdir; component test harness-i bütün transport/middleware davranışını əvəz etmir.

### 6.6. Priority və rank

- Priority biznes vacibliyidir: `low < medium < high < urgent`.
- Rank eyni project/status sütununda vizual sıralama mövqeyidir.

Status dəyişəndə task target sütunun sonuna append olunur. Açıq reorder manager-only-dir. Priority-ni dəyişmək board rank-ını avtomatik dəyişməməlidir.

### 6.7. Activity və Notification

- Activity audit tarixçəsidir; canonical event və safe old/new summary saxlayır.
- Notification konkret recipient üçün inbox mesajıdır və read state daşıyır.

Bir status dəyişikliyi Activity yaza və uyğun watcher-lərə notification göndərə bilər, amma bunlar eyni record və eyni məqsəd deyil.

### 6.8. Media və TaskAttachment

- `Media` private binary, detected MIME, random path, checksum, stream və physical cleanup sahibidir.
- `TaskAttachment` Tasks modulunda Task ilə Media arasındakı explicit association-dır.

`TaskAttachmentService::uploadMany()` storage write-dan sonra DB transaction daxilində Media metadata + association + Activity yaradır; failure-də storage compensation edir. Tasks kodu faylı birbaşa storage-a yazmır.

### 6.9. Transaction və compensation

DB transaction yalnız database dəyişikliklərini rollback edir. Fayl sistemi həmin transaction-a daxil deyil. Buna görə media flow-da iki mexanizm birlikdədir:

- DB state üçün `DB::transaction`;
- artıq yazılmış fayllar üçün `compensateStored()`.

`TaskStatusService::change()` isə task lock, version check, transition, rank, Activity və notification orchestration-ını bir top-level transaction-da saxlayır.

### 6.10. DTO nədir?

`ChangeTaskStatusData` validated `status` və `expected_version` dəyərlərini readonly obyekt kimi service-ə daşıyır. Controller/service-ə bütün request-i və ya `request()->all()` ötürülmür. Bununla service-in input contract-ı kiçik, typed və test edilən olur.

### 6.11. High coupling və loose coupling

Hazırkı modular monolith-də bəzi birbaşa əlaqələr məqsədlidir. Məsələn, `TaskStatusService` Projects membership, Activity recorder, notification service və rank service ilə birbaşa əməkdaşlıq edir. Bu, gizli deyil və `ARCHITECTURE_DECISIONS.md`-də qəbul edilib.

Hər dependency üçün event bus/interface adapter yaratmaq avtomatik “daha yaxşı” deyil. Davranış sabitləşənədək speculative abstraction yaradılmamalıdır; geniş loose-coupling refaktoru roadmap scope-udur.

### 6.12. Unit, feature, Playwright və manual test fərqi

Onlar eyni şeyi yoxlamır:

- Unit: framework/DB olmadan qaydanı yoxlayır. Nümunə: `TaskDomainRulesTest.php` transition table, timestamp və rank helper-ləri.
- Feature/integration: Laravel container, route, DB, policy, service və response-u birlikdə yoxlayır. Nümunə: `AuthorizationMatrixTest.php`.
- Playwright: real browser, native HTML validation, JavaScript, responsive DOM, CSRF/cookie və interaction-u yoxlayır.
- Manual: gözlənilməyən UX, mətn, discoverability və insan davranışını tapmaq üçün exploratory yoxlamadır.

Project Key problemi bunun konkret nümunəsidir: feature test backend-ə lowercase göndərib pass edir, amma real browser HTML pattern səbəbilə request-i göndərmir. Buna görə yalnız bir test qatı kifayət etmir.

### 6.13. Paketlər necə seçilir və istifadə olunmayan paket “arxa planda işləyirmi?”

Bir paket yalnız “lazım ola bilər” deyə seçilməməlidir. Qəbul edilmiş məhsul/texniki ehtiyac, maintenance vəziyyəti, Laravel/PHP uyğunluğu, təhlükəsizlik və alternativin qiyməti yoxlanmalıdır.

Cari nümunələr `composer.json:10-16`-dadır:

- Laravel — framework;
- Sanctum — PAT authentication;
- Livewire — yalnız dörd təsdiqlənmiş component;
- nwidart/laravel-modules — modul discovery/struktur;
- Spatie Permission və Activitylog — rol/permission və audit.

Paket kodu process-də fasiləsiz “işləmir”. Composer autoload class-ları əlçatan edir; package discovery/service provider boot zamanı route, binding və listener qeydiyyatdan keçirə bilər; faktiki əməliyyat isə uyğun request/event/use case gələndə icra olunur. Məsələn, Livewire package update route-u qeydiyyata alır, `modules_statuses.json` isə yalnız Projects, Tasks, Activity, Dashboard və Media modullarını aktiv göstərir.

## 7. Hesabatlarda ayrıca deyilməyən, amma vacib pattern və risklər

### 7.1. Branch-də “fixed” statusu inteqrasiya sübutu deyil

Junior branch-ində düzəlişin və testin pass olması mümkündür. Main/current checkout-da həmin fayl/diff/test yoxdursa məhsul baxımından iş hələ tamamlanmayıb.

Praktik inteqrasiya qaydası:

1. hər junior branch/commit reference təqdim etsin;
2. eyni problemin bir neçə implementasiyası diff və acceptance meyarına görə müqayisə edilsin;
3. seçilən dəyişiklik current branch-ə inteqrasiya edilsin;
4. conflict həllindən sonra authoritative docs və testlər həmin checkout-da yenidən işlədilsin;
5. yalnız bundan sonra “fixed” statusu verilsin.

### 7.2. Test sayı commit/checkout-a bağlanmalıdır

Hesabatlarda 250, 253 və 258 test nəticələri var, current checkout isə 247 test göstərir. Bu, branch-lər fərqli olduqda normal ola bilər, amma nəticə hansı koda aid olduğu bilinməsə qəbul sübutu zəifləyir.

Hər test reportu ən azı bunları daşımalıdır:

- branch/commit reference;
- dəqiq komanda;
- runtime və DB profili;
- pass/fail/test/assertion sayı;
- skipped/focused test olub-olmaması;
- report vaxtı.

### 7.3. Regression problemi yaradan qatın özündə yazılmalıdır

- HTML native validation bug-u yalnız backend feature testi ilə bağlanmır; browser və JS-disabled ssenari lazımdır.
- Livewire persistent middleware bug-u yalnız `Livewire::test()` ilə bağlanmır; real update HTTP transport-u lazımdır.
- UI discoverability route testlə bağlanmır; görünən link/control assertion-u və browser journey lazımdır.

### 7.4. Güclü mövcud patternlər qorunmalıdır

Cari `composer test` bütün 247 testi keçir. Kod araşdırmasında aşağıdakı düzgün sərhədlər təsdiqlənir və yuxarıdakı fix-lər bunları pozmamalıdır:

- Controller-lər repository/Eloquent/DB/Storage çağırmır; service/query boundary istifadə edir.
- Actor-visible və parent-scoped route binding IDOR riskini azaldır.
- Sanctum ability Policy-ni bypass etmir.
- Task status/rank optimistic concurrency və row lock istifadə edir.
- Media binary lifecycle Media modulunda, Task–Media association Tasks modulunda qalır.
- Multi-file upload DB transaction + physical compensation modelini istifadə edir.
- Completed/archived project mutation-ları policy və service qatında bloklanır.

### 7.5. Sənəd və kod eyni dəyişiklikdə yenilənməlidir

Junior branch-indən fix inteqrasiya ediləndə yalnız kodun gətirilməsi kifayət deyil. Cari həqiqəti dəyişən hər iş uyğun test və authoritative `docs-ibp` sənədini birlikdə daşımalıdır. Alternativ status/handoff sənədi yaradılmamalıdır; bu `junior-all.md` yalnız analiz və gələcək inteqrasiya üçün iş siyahısıdır.

## 8. Tövsiyə olunan icra ardıcıllığı

1. **Livewire active-user boundary** — security prioriteti; branch diff-i review, real HTTP regression, sonra SQLite/MySQL gate.
2. **Project Key browser contract** — form/pattern/JS/progressive enhancement və browser regression.
3. **Watcher task-detail UI** — read model + policy-aware controls + Web/E2E test.
4. **Status page consistency** — redirect/full refresh və ya tam event-driven refresh qərarı.
5. **Manage labels linki** — kiçik, ayrıca UX dəyişiklik.
6. **Product qərarları** — last-admin recovery və optional notification open/read semantics.
7. **Bütün inteqrasiyalardan sonra** authoritative docs, SQLite, dedicated Herd MySQL, architecture/static, format, build və məqsədli Playwright gate-ləri.

## 9. Bu analiz zamanı aparılan yoxlamalar

- `report-latest` daxilində bütün 6 Markdown və 4 DOCX faylın məzmunu oxundu.
- Bütün məcburi `docs-ibp` business/technical/module sənədləri və əlaqəli API/environment/release sənədləri oxundu.
- Cari Project Key, Livewire, watcher, status UI, labels, notifications, auth, authorization, test və package kodu yoxlanıldı.
- `php artisan route:list --path=livewire --json` — Livewire update route middleware-ləri təsdiqləndi.
- `composer test` — **PASS: 247 test, 1861 assertion**.

İşlədilməyən yoxlamalar:

- Dedicated MySQL suite işlədilmədi; bu analiz `.env.testing` credential-larına ehtiyac yaratmadı və real DB gate-i kod inteqrasiyasından sonra mənalıdır.
- Playwright/browser automation yenidən işlədilmədi; tapşırıq analysis-only idi və browser automation ayrıca icazə tələb edir.
- DOCX vizual səhifə renderi LibreOffice olmadığı üçün mümkün olmadı; bütün DOCX mətn və cədvəl məzmunu OpenXML-dən çıxarılıb oxundu. Layout bu texniki nəticələrə təsir etmir.

## 10. Yekun

Juniorların əsas tapıntıları düzgün istiqamətə işarə edir. Xüsusilə Project Key və Livewire active-user problemləri bir neçə nəfər tərəfindən müstəqil qeyd edilib. Bəzi juniorlar bunları öz branch-lərində düzəltmiş ola bilər, lakin düzəlişlər hazırkı checkout-da görünmür. Buna görə current branch üçün həmin işlər açıq saxlanmalıdır.

Ən kritik iş Livewire request-lərində account statusunun fail-closed qorunmasıdır. Sonra browser və backend Project Key müqaviləsi uyğunlaşdırılmalı, mövcud watcher capability-si UI-da əlçatan edilməli və status dəyişəndən sonra task səhifəsinin bütün hissələri eyni state-i göstərməlidir. Mövcud controller/service/repository, scoped binding, concurrency və media compensation sərhədləri isə yaxşı qurulub və düzəlişlər zamanı qorunmalıdır.
