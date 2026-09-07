# Məhsul təsviri

## Məqsəd

TaskFlow proqram və əməliyyat komandaları üçün daxili, tək təşkilatlı, Kanban yönümlü iş izləmə sistemidir. Məhsul kiçik Jira tipli axın verir, lakin müəssisə səviyyəli konfiqurasiya mürəkkəbliyini daşımır.

Sistem aşağıdakı tam dövrü əhatə edir:

- daxili istifadəçi hesabının administrator tərəfindən yaradılması və idarəsi;
- layihənin yaradılması, aktivləşdirilməsi, üzvlərin və həyat dövrünün idarəsi;
- backlog-da işin planlanması və board üzərindən icrası;
- təyinat, status, şərh, label, watcher və bildiriş əməkdaşlığı;
- şəxsi media yükləmə, preview, download və silmə;
- görünürlük qaydalarına uyğun audit tarixi və dashboard;
- Sanctum token-i ilə REST API v1.

## Məhsul sərhədi

TaskFlow bir tətbiq, bir təşkilat, bir verilənlər bazası və bir deploy vahididir. Bir work item yalnız bir layihəyə, bir reporter-ə və ən çox bir assignee-yə malikdir. Maraqlı istifadəçilər çoxlu assignee deyil, watcher kimi modelləşdirilir.

UI server-rendered Blade, Tailwind CSS və məqsədli vanilla JavaScript-dən ibarətdir. Livewire yalnız dörd məhdud komponentdə istifadə olunur. Ayrıca SPA və ya mobil tətbiq yoxdur.

## Cari funksional əhatə

- Qlobal rollar: `admin`, `project_manager`, `member`.
- Layihə rolları: `manager`, `member`.
- Layihə statusları: `draft`, `active`, `completed`, `archived`.
- İş növləri: `task`, `bug`, `story`, bir səviyyəli `subtask`.
- Prioritetlər: `low < medium < high < urgent`.
- Workflow: `backlog`, `todo`, `in_progress`, `review`, `done`, `cancelled`.
- Layihə daxilində `PAY-42` tipli issue açarı və ayrıca sıralama rank-ı.
- Layihəyə məxsus label-lar, watcher-lər, şərhlər və media.
- Database/in-app bildirişləri, Activity auditi və rol əsaslı Dashboard.
- `/api/v1` altında 46 adlandırılmış əməliyyat.

## Cari əhatəyə daxil olmayanlar

Aşağıdakılar implementasiya edilməyib və cari müqaviləyə daxil deyil:

- workspace və multi-tenancy;
- açıq qeydiyyat;
- bir iş üçün birdən çox assignee;
- sprint, epic, release, story point və capacity planning;
- custom field, custom type, custom workflow və custom permission scheme;
- component və ümumi category sistemi;
- işlərarası dependency və recurring task;
- automation, webhook və üçüncü tərəf inteqrasiyası;
- rich-text/WYSIWYG redaktoru;
- public media URL-ləri;
- API notification inbox;
- 2FA, malware scanning, quarantine və digər qabaqcıl təhlükəsizlik əməliyyatları.

Bu mövzuların bir qismi [`ROADMAP.md`](../../ROADMAP.md) daxilində gələcək seçim kimi saxlanılır; roadmap cari funksionallıq deyil.

## Məhsulun qəbul edilmiş vəziyyəti

Roadmap-xarici əsas məhsul axınları Web, API və uyğun Livewire girişlərində eyni servis qaydalarını istifadə edir. Avtomatlaşdırılmış SQLite/MySQL, architecture, security, build, format və real-browser qəbul gate-ləri [`RELEASE_BASELINE.md`](../technical/RELEASE_BASELINE.md) sənədində qeyd olunub.

