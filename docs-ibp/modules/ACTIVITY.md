# Activity modulu

## Junior üçün əvvəl bu sualı ver: kim, nəyi, necə dəyişdi?

Murad PAY-42 işini `todo`-dan `in_progress`-ə keçirirsə sonradan komanda «bunu kim və nə vaxt etdi?» sualını verə bilər. Activity həmin dəyişiklik haqqında təhlükəsiz audit qeydi saxlayır.

**Actor** Muraddır, yəni əməliyyatı edən. **Subject** PAY-42 işidir, yəni əməliyyatın aid olduğu obyekt. **Properties** isə təsdiqlənmiş əlavə məlumatdır: məsələn, köhnə və yeni status. Bu, bütün request-in və ya task modelinin kopyası deyil.

Activity yeni statusu hesablamır, icazəni vermir və özü bildiriş göndərən ümumi event bus deyil. Status service-i düzgün dəyişiklik etdikdən sonra recorder-i çağırır. Auditdəki `event` adı Laravel `event(...)` dispatch-i ilə qarışdırılmamalıdır.

Şəkildəki bütün qutuları bir-bir açan [Activity dərsini](../diagrams/flows/activity.md) əvvəl oxu. Orada actor/subject, morph əlaqəsi, `schema_version`, sanitizer, görünürlük və rollback real kodla ayrıca izah olunur. Sonra aşağıdakı texniki müqaviləyə qayıt.

## Məsuliyyət

Activity Spatie Activitylog üzərində canonical audit event-ləri, təhlükəsiz payload, vahid recorder və actor-visible read query-lərin sahibidir.

## Yazı qaydası

Use-case service uğurlu state change daxilində `ActivityRecorder` çağırır. Event enum-dan seçilir; actor və subject explicit verilir. Safe summary və təsdiqlənmiş old/new sahələrdən başqa request dump saxlanmır.

Recursive sanitizer password, token, authorization, cookie, secret, path, checksum və content/body kimi həssas hissələri daşıyan açarları atır; depth 8, array üzrə 100 element və string üzrə 500 byte limiti tətbiq edir. Etibarsız UTF-8/control-byte mətn və generic obyektlər qəbul edilmir. `_changed` boolean və `_count` integer summary-ləri ayrıca saxlanıla bilər. Sanitizer secret məzmununu bütün adi string-lərdə aşkarlayan skaner deyil; generic model serialization və credential caller tərəfindən audit payload-a verilməməlidir.

## Oxu qaydası

Global, project və task Activity list/API actor-un Projects/Tasks visibility scope-u ilə başlayır. Filter ID-si əvvəl global mövcudluq yoxlaması ilə oracle yaratmır; inaccessible və nonexistent record eyni safe nəticə verir. Presentation yalnız əvvəlcədən hazırlanmış actor/subject məlumatını göstərir və lazy loading etmir.

## Asılılıqlar və səth

Projects və Tasks Activity yazır. Host user/account event-lərini yazır. Dashboard recent activity oxuyur. API-də global/project/task olmaqla 3 read endpoint, Web-də scope edilmiş Activity səhifəsi var.

Event mapping, sanitizasiya, visibility, filter isolation, payload budget və presentation query count unit/feature/security testləri ilə qorunur.

## Activity event-i Laravel event-i deyil

`ActivityRecorder::record()` `ActivityEvent` enum dəyəri ilə `activity_log` audit sətrini yazır və `properties.schema_version=1` əlavə edir. Bu yazı öz-özlüyündə Laravel listener dispatch etmir. Məsələn, `TaskStatusService` statusu yazdıqdan sonra audit recorder və notification service-i birbaşa çağırır.

R1-dəki `LearningEntryPublished` isə `event(...)` ilə Laravel dispatcher-ə verilən ayrıca obyektdir; `ShouldDispatchAfterCommit` tətbiq edir və Insights listener-i tərəfindən qəbul olunur. Learning modulları Activity recorder-a bağlı deyil. «Event» sözünün iki kontekstdə işlənməsi bu axınların eyni olduğu demək deyil.

Kod: `Modules/Activity/app/Services/ActivityRecorder.php` və `Modules/Activity/app/Enums/ActivityEvent.php`. [Sadə müqayisə](../extended/activity-vs-laravel-event.md), [diagramlar](../diagrams/README.md).
