# Terminlər lüğəti

| Termin | Mənası |
|---|---|
| TaskFlow | Tək təşkilat üçün daxili issue/work-item izləmə sistemi |
| Work item / issue | Məhsul termini; kodda `Task` modeli və `/tasks` API yolu ilə təmsil olunur |
| Reporter | İşi yaradan istifadəçi; sxemdə `creator_id` |
| Assignee | İşə cavabdeh sıfır və ya bir istifadəçi |
| Watcher | İşdə maraqlı olan və uyğun bildiriş alan layihə üzvü; edit səlahiyyəti vermir |
| Global role | Təşkilat səviyyəli `admin`, `project_manager`, `member` rolu |
| Project role | Konkret layihədə `manager` və ya `member` rolu |
| Project owner | Layihəni sahib kimi idarə edən və manager qalmalı olan istifadəçi |
| Project key | `PAY` kimi unikal böyük hərfli layihə kodu |
| Display key | Project key və lokal issue nömrəsi; məsələn `PAY-42` |
| Issue sequence | Layihənin növbəti work item nömrəsini atomik ayıran sayğac |
| Backlog | Yeni işlərin başladığı və ayrıca rank ilə planlandığı workflow vəziyyəti |
| Board | Work item-lərin workflow statuslarına görə sütun görünüşü |
| Rank | Project/status sütunu daxilində sıralama mövqeyi; prioritet deyil |
| Workflow | Sabit statuslar və icazəli keçid cədvəli |
| Subtask | Eyni layihədə standard parent tələb edən, özü parent ola bilməyən bir səviyyəli iş |
| Label | Layihəyə məxsus ad/slug/rəng təsnifatı |
| Activity | Təhlükəsiz, canonical və görünürlük-scope edilmiş audit qeydi |
| Notification | Database/in-app, Web-only istifadəçi bildirişi |
| Media | Şəxsi binary və server tərəfindən aşkarlanmış metadata |
| Attachment | Tasks modulunun Task ilə Media arasındakı explicit association qeydi |
| DTO | Validasiya olunmuş input-u use case-ə daşıyan readonly, məqsədli data obyekti |
| Query service | Controller üçün tam scope edilmiş və presentation-ready read nəticəsi hazırlayan application boundary |
| Repository | Eloquent query, persistence, eager loading, pagination və lock sahibi qat |
| Policy/Gate | Konkret actor və record kontekstində authorization qərarı |
| Sanctum ability | API token route ailəsini daraldan, policy-ni əvəz etməyən capability |
| Optimistic concurrency | `expected_version` vasitəsilə stale status/rank yazısını 409 ilə rədd etmə |
| Compensation | Xarici storage DB transaction-a daxil olmadıqda uğursuz batch nəticələrini geri təmizləmə |
| Read-only project | `completed` və ya `archived` layihə; görünə bilər, mutasiya qəbul etmir |
| Roadmap | Cari implementasiyaya daxil olmayan, ayrıca qərar və gate tələb edən gələcək işlər |
