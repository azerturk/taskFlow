# TaskFlow-u ilk dəfə oxuyan junior üçün

## Bu bələdçi nə üçündür?

Bu kodbazanı öyrənmək üçün əvvəl bütün class adlarını əzbərləməyə ehtiyac yoxdur. Əvvəl istifadəçinin nə etmək istədiyini anla, sonra həmin istəyin kodda hansı yoldan keçdiyini izlə.

Sənədlərdə texniki qaydalar saxlanılıb. Bu bələdçi və hər diagramın yanındakı ayrıca `.md` faylı həmin qaydaları gündəlik nümunə ilə açır. Şəkli tək oxumaq çətindirsə problem səndə deyil: şəkil xəritədir, yanında olan mətn isə yolun izahıdır.

Buradakı adlar və rəqəmlər öyrətmək üçün nümunədir; real istifadəçilərin və lokal database-in çıxarışı deyil.

## Bir istifadəçi hekayəsi ilə başlayaq

Aysel layihə manager-idir. Murad həmin layihənin üzvüdür. Komanda ödəniş səhifəsində səhv tapıb.

### 1. Əvvəl layihə hazırlanır

Aysel `PAY` açarlı layihə yaradır. Layihə əvvəl `draft` olur: adı və üzvləri hazırlamaq mümkündür, amma hələ iş/şərh/fayl əlavə etmək olmaz. Aysel komandanı əlavə edib layihəni `active` edir.

Kodda bunun sahibi **Projects** moduludur. Layihənin statusu ilə işin statusu fərqlidir: layihə `active`, onun içindəki yeni iş isə `backlog` ola bilər.

Oxu: [layihə axınının sadə izahı](diagrams/flows/project.md).

### 2. Murad bug report edir

Murad title, type və priority göndərir. Sistem işi `backlog` statusunda yaradır, reporter kimi Muradı götürür, project-local nömrə və rank hesablayır. Məsələn, nəticə `PAY-42` ola bilər.

Murad request-ə «məni manager et», «işi dərhal done yarat» və ya «rank=1 olsun» yazmaqla serverin qaydalarını dəyişə bilməməlidir. Bu sahələrin sahibi serverdir.

Kodda bunun sahibi **Tasks** moduludur. Oxu: [işin yaradılması](diagrams/flows/task-create.md).

### 3. İş bir nəfərə tapşırılır

Aysel işi Murada assign edir. Murad assignee olur: işin icrasına cavabdehdir. Başqa üzv Leyla da işi görə bilər, çünki görünürlük assignee-yə yox, layihə üzvlüyünə bağlıdır.

Leyla dəyişikliklərdən xəbər tutmaq istəyirsə watcher ola bilər. Bu, ona manager səlahiyyəti və ya əlavə edit hüququ vermir.

Oxu: [assignment](diagrams/flows/assignment.md), [watcher, şərh və bildiriş](diagrams/flows/collaboration.md).

### 4. Status dəyişir

İş əvvəl `backlog`-dan `todo`-ya keçir, sonra Murad `todo`-dan `in_progress`-ə keçirərək icraya başlayır. `backlog → in_progress` birbaşa keçidi yoxdur. Backend onun assignee olduğunu, layihənin active olduğunu, keçidin icazəli olduğunu və gördüyü versiyanın köhnəlmədiyini yoxlayır.

Məsələn, səhifədə `version=3` görüb `expected_version=3` göndərir. Arada başqa dəyişiklik versiyanı 4 edibsə status yazısı 409 conflict alır. Bu, serverin «köhnə səhifədən yeni vəziyyətin üstünə yazma, əvvəl işi yenidən oxu» deməsidir.

Oxu: [status axını](diagrams/flows/status.md), [version nümunələri](extended/optimistic-concurrency.md).

### 5. Fayl əlavə olunur

Murad screenshot əlavə edir. Tasks «bu istifadəçi bu işə fayl əlavə edə bilərmi?» və «fayl hansı işə bağlanır?» hissəsinə sahibdir. Media isə faylın real tipini, ölçüsünü, private saxlanmasını və stream edilməsini idarə edir.

Faylın private olması o deməkdir ki, təsadüfi public URL ilə açılmır. Download zamanı da işə baxış icazəsi yoxlanır.

Oxu: [upload](diagrams/flows/media-upload.md), [private stream](diagrams/flows/media-stream.md), [silinmə](diagrams/flows/media-delete.md).

### 6. Tarixçə və dashboard göstərilir

Status dəyişikliyi haqqında «kim nəyi dəyişdi?» audit qeydi Activity-də saxlanır. Uyğun watcher-lərə database bildirişi yarana bilər. Dashboard isə Muradın görə bildiyi məlumatlardan sayğac və siyahılar hazırlayır.

Activity auditi, istifadəçi notification-u və Laravel event-i ayrı anlayışlardır. Birinin yaranması avtomatik o birilərinin də işlədiyini göstərmir.

Oxu: [Activity-ni sıfırdan anlayaq](diagrams/flows/activity.md), [Dashboard](diagrams/flows/dashboard.md), [audit və Laravel event fərqi](extended/activity-vs-laravel-event.md).

## Bu hekayənin arxasındakı kod yolu

Bir request-i poçtla gələn iş tapşırığı kimi düşün. Hər qatın ayrı sualı var:

| Qat | Cavab verdiyi sual | Status nümunəsi |
|---|---|---|
| Route | İstək hansı girişə çatmalıdır? | Status endpoint-i |
| Controller / Livewire | Gələn istəyi application çağırışına necə çevirim? | Yoxlanmış input-dan DTO qurur |
| Form Request | Input düzgün formadadırmı? | Status dəyəri və version forması |
| Policy / ability | Bu actor bu əməliyyatı edə bilərmi? | Assignee/manager və token sərhədi |
| DTO | Use case-ə hansı məlumat ötürülür? | `ChangeTaskStatusData` |
| Service | Bu vəziyyətdə dəyişiklik düzgündürmü və birlikdə nələr edilməlidir? | `TaskStatusService::change()` |
| Repository | Database-dən necə oxuyaq/yazaq/kilidləyək? | Cari task və rank write-ları |
| Resource / Blade | Hazır nəticəni necə göstərək? | JSON və ya yenilənmiş səhifə |

Bu, metodların bütün runtime sırasını göstərən stack trace deyil, məsuliyyət xəritəsidir; middleware və binding controller-dən əvvəl də işləyir. Ətraflı: [request qatları](diagrams/system/request-layers.md).

Controller-də hər şeyi yazmaq ilk anda asan görünə bilər. Amma Web, API və Livewire eyni qaydanı istifadə etməlidir. Qayda service-də olduğuna görə bir girişdən edilən əməliyyat digərindən fərqli işləməməlidir.

## Diagramı necə oxumalı?

Əvvəl həmin SVG-nin yanındakı eyni adlı Markdown səhifəni aç. Məsələn, `activity.svg` üçün [activity.md](diagrams/flows/activity.md).

1. «Nə üçündür?» hissəsində istifadəçinin məqsədini oxu.
2. Terminləri ilk dəfə orada öyrən; başa düşmədiyin sözü sadəcə keçmə.
3. Nümunə istifadəçi və əməliyyatı yadda saxla.
4. Şəkildə qutuları nömrəli izahla birlikdə izlə.
5. Oxun çağırış, cavab, dependency və ya logical relation olduğunu səhifənin öz izahından yoxla. Bütün şəkillərdə kəsik xəttin mənası eyni deyil.
6. Kiçik kod parçasını oxu; sonra source linkindən tam metodun əvvəlini və sonunu gör.
7. Xəta budağında database-də nəyin qaldığını ayrıca düşün.
8. Sondakı özünüyoxlama suallarını mətnə baxmadan cavablandır.

Şəkildə eyni anda bütün xətaları göstərmək mümkün deyil. Ona görə mətn alternativi şəkildən daha genişdir; «diagramda ox yoxdur» təkbaşına «kodda belə davranış yoxdur» sübutu deyil.

## Məhsul və R1 laboratoriyasını ayır

Projects, Tasks, Media, Activity, Dashboard gündəlik məhsuldur. Auth, admin, token və notification Laravel host tətbiqindədir.

LearningCatalog və LearningInsights isə kiçik tədris praktikasıdır. Onlarda ayrıca UI/HTTP endpoint yoxdur. Məqsəd contract, event, projection və recovery-ni məhsula toxunmadan öyrənməkdir.

Catalog-un public feed-i PHP interface-idir, `/api/v1` URL-i deyil. Onun event listener-i synchronous işləyir, queue worker tələb etmir. Burada outbox/inbox və avtomatik retry yoxdur.

Laboratoriyanı belə oxu: [iki cədvəl](diagrams/labs/lab-data.md) → [public feed](diagrams/labs/public-feed.md) → [publish/event](diagrams/labs/publish.md) → [duplicate](diagrams/labs/duplicate.md) → [rebuild](diagrams/labs/rebuild.md).

## Kod oxuyarkən dörd sual ver

**Kim çağırır?** Method HTTP adapter-dən, başqa service-dən və ya listener-dən gələ bilər. Service çağırmaq öz-özünə user authorization etmir.

**Qayda haradadır?** Məsələn, button-un gizlənməsi icazə yoxlaması deyil. Backend policy və service qaydalarına bax.

**Transaction harada bitir?** Exception-dan sonra database nəticəsini bilmək üçün commit-in əvvəl, yoxsa sonra olduğunu tap. Disk faylı DB rollback ilə öz-özünə silinmir.

**Bunu hansı test sübut edir?** Yalnız metodun adına baxma. Testdə setup, action, assertion və failure halını tap. Test bütün mümkün davranışı deyil, yazılmış ssenarini yoxlayır.

## Təhlükəsiz öyrənmə qaydası

Kod və test oxumaq real bazada migration/seeder işlətməyi tələb etmir. `.env` və `.env.testing` məzmununu report-a, mesajlaşmaya və AI prompt-una kopyalama. Test əmrləri və xüsusilə destruktiv DB əməliyyatları üçün [mühit](technical/ENVIRONMENT.md) və [test](technical/TESTING.md) qaydalarını əvvəl oxu.

Bir anlayış sənəddə izah olunubsa bu, onu indi implementasiya etmək tapşırığı deyil. `ROADMAP.md` gələcək scope-dur. Cari davranış, laboratoriya davranışı və nəzəri nümunə sənədlərdə ayrıca göstərilir.

## Mövzu seçərək davam et

- [Sistemin ümumi xəritəsinin izahı](diagrams/system/context.md)
- [Hər diagram üçün ayrıca dərs](diagrams/README.md)
- [Anlayış və müqayisələrin ayrıca faylları](extended/README.md)
- [Kodbazada fayl tapmaq bələdçisi](technical/CODEBASE_GUIDE.md)
- [Qısa terminlər lüğəti](business/GLOSSARY.md)
- [Əsas sənəd mərkəzi](README.md)
