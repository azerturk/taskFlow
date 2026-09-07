# Activity modulu

## Məsuliyyət

Activity Spatie Activitylog üzərində canonical audit event-ləri, təhlükəsiz payload, vahid recorder və actor-visible read query-lərin sahibidir.

## Yazı qaydası

Use-case service uğurlu state change daxilində `ActivityRecorder` çağırır. Event enum-dan seçilir; actor və subject explicit verilir. Safe summary və təsdiqlənmiş old/new sahələrdən başqa request dump saxlanmır.

Recursive sanitizer password, token, authorization, cookie, secret, path, checksum və binary tipli açar/dəyərləri atır; depth, item və string byte limitləri tətbiq edir. Generic model serialization və credential heç vaxt audit payload-a verilmir.

## Oxu qaydası

Global, project və task Activity list/API actor-un Projects/Tasks visibility scope-u ilə başlayır. Filter ID-si əvvəl global mövcudluq yoxlaması ilə oracle yaratmır; inaccessible və nonexistent record eyni safe nəticə verir. Presentation yalnız əvvəlcədən hazırlanmış actor/subject məlumatını göstərir və lazy loading etmir.

## Asılılıqlar və səth

Projects və Tasks Activity yazır. Host user/account event-lərini yazır. Dashboard recent activity oxuyur. API-də global/project/task olmaqla 3 read endpoint, Web-də scope edilmiş Activity səhifəsi var.

Event mapping, sanitizasiya, visibility, filter isolation, payload budget və presentation query count unit/feature/security testləri ilə qorunur.
