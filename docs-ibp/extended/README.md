# Sadə izahlar və müqayisələr

Bu bölmə junior developer-in kodu oxuyarkən verdiyi “niyə belədir?” suallarına cavab verir. Mətnlər yeni funksiya sifarişi deyil və əsas biznes/texniki qaydaların yerini tutmur.

Bu bölmədə Əhməd, Fidan, Rauf və Leyla ilə verilən həyat hekayələri izah üçün qurulmuş nümunələrdir; real junior report-u, real istifadəçi əməliyyatı və ya yeni test run-u kimi təqdim edilmir. Faktiki implementasiya və test sübutu ayrıca source keçidlərindədir.

## Hansı nümunə harada işləyir?

- **Məhsul:** Projects, Tasks, Media, Activity, Dashboard və host tətbiqdə işləyən davranış.
- **R1 laboratoriyası:** LearningCatalog və LearningInsights daxilində ayrıca praktik nümunə. Məhsul modulları bu laboratoriyadan istifadə etmir.
- **Yalnız anlayış:** outbox, inbox, broker və microservice kimi izah edilən, lakin burada implementasiya edilməyən yanaşma.

Bir anlayışı oxumaq onu bütün sistemə tətbiq etmək göstərişi deyil. Hazırkı davranışı [arxitektura](../technical/ARCHITECTURE.md), [biznes qaydaları](../business/BUSINESS_RULES.md) və real kodla birlikdə oxu.

## API, əlaqələr və modul sərhədi

1. [REST API və modulun public API-si](rest-api-vs-module-public-api.md)
2. [Birbaşa çağırış və event](direct-call-vs-event.md)
3. [Modular monolith və microservice](modular-monolith-vs-microservices.md)
4. [Modulun aktivliyi və optional listener](module-enabled-vs-optional-listener.md)
5. [Architecture testləri və Deptrac](architecture-tests-vs-deptrac.md)

## Event, məlumat və etibarlılıq

1. [Event və queue](event-vs-queue.md)
2. [After-commit](after-commit.md)
3. [Outbox və inbox](outbox-vs-inbox.md)
4. [Idempotency və exactly-once](idempotency-vs-exactly-once.md)
5. [Projection və əsas məlumat mənbəyi](projection-vs-source-of-truth.md)
6. [Rebuild və yenidən publish](rebuild-vs-republish.md)
7. [Transaction və compensation](transaction-vs-compensation.md)

## Məhsul kodunu oxumaq

1. [Controller, service və repository](controller-service-repository.md)
2. [DTO, model və Resource](dto-model-resource.md)
3. [Validation, authorization və invariant](validation-authorization-invariant.md)
4. [Rollar, permission, policy və ability](roles-permissions-policies-abilities.md)
5. [Reporter, assignee və watcher](reporter-assignee-watcher.md)
6. [Rank, priority və issue number](rank-priority-issue-number.md)
7. [Optimistic concurrency](optimistic-concurrency.md)
8. [Lazy loading və eager loading](lazy-vs-eager-loading.md)
9. [Activity və Laravel event-i](activity-vs-laravel-event.md)
10. [Audit, notification və əməliyyat log-u](audit-notification-operational-log.md)
11. [HTTP xəta kodlarını necə oxuyaq?](http-errors.md)
12. [Parent-scoped resource, IDOR və safe 404](nested-resources-and-safe-404.md)

## Testlər

1. [PHPUnit və Pest](phpunit-vs-pest.md)
2. [RefreshDatabase və DatabaseMigrations](refresh-database-vs-database-migrations.md)
3. [Test növləri](test-types.md)

## Necə oxumalı?

Əvvəl sadə cavabı, sonra kod parçasını və axını oxu. Kod parçaları tam class deyil: yanında verilən keçiddən orijinal metodu aç. Dəyişən adları və enum-lar qəsdən real koddakı kimi saxlanılıb; izah dili Azərbaycan dilidir.

Hər məqalədə anlayışı əzbərləmək əvəzinə aşağıdakı ardıcıllıqla işləmək olar:

1. **Termini anla:** ilk dəfə gördüyün sözün sadə açılışını oxu. Termin ingilis dilindədirsə onu kodda tanımaq üçün saxlanılıb.
2. **Həyat ssenarisini izlə:** əvvəl hansı məlumat var, kim nə edir, sonra nə qalır suallarına cavab ver.
3. **Kodun sətirlərini oxu:** snippet-in hansı sətri hansı məsuliyyətə sahibdir? Bütün parçanı copy/paste edib işlətmək tapşırığı deyil.
4. **Yanlış variantı düşün:** alternativ niyə burada səhv və ya uyğun olmayan nəticə yaradır?
5. **Xəta yolunu izlə:** request exception alsa DB, disk, event və projection-dan hansı nəticə qalır?
6. **Özünü yoxla:** cavaba baxmadan suallara qısa cavab ver, sonra verilmiş cavabla müqayisə et.
7. **Orijinalı aç:** kod/test keçidindən real metodu və assertion-u oxu. Məqalənin faktını source ilə tutuşdur.

### İlk həftə üçün praktik oxuma nümunəsi

Əhməd “event varsa queue da var” düşünür. Əvvəl [event və queue](event-vs-queue.md), sonra [after-commit](after-commit.md) məqaləsini oxuyur. [Publish diagramının izahında](../diagrams/labs/publish.md) addımları izləyir. Sonra real provider və listener-də `ShouldQueue` olmadığını, event-də isə `ShouldDispatchAfterCommit` marker-i olduğunu görür. Sonda “nə vaxt işləyir?” ilə “hansı proses işləyir?” suallarını ayrı cavablandırır.

Fidan exception gördükdə publish-in rollback olduğunu düşünür. O, [rebuild və republish](rebuild-vs-republish.md), [projection](projection-vs-source-of-truth.md) və [transaction/compensation](transaction-vs-compensation.md) məqalələrini müqayisə edir. Məqsəd yeni kod yazmaqdan əvvəl failure-dan sonra hansı state-in qaldığını düzgün deyə bilməkdir.

### Bu bölmədə nə etməməli?

- Həyat nümunəsindəki nəzəri outbox/inbox addımlarını cari implementasiya kimi qəbul etmə.
- Anlayış xoşuna gəldi deyə bütün məhsul modullarını event/contract refaktoruna başlama.
- Testdəki direct DB assertion-ı production controller-də query yazmağa icazə kimi oxuma.
- Başqa sistemdə düzgün olan pattern-in burada avtomatik scope-a daxil olduğunu düşünmə.

Hər SVG üçün eyni adlı Markdown izahı da var. Şəkil ümumi xəritəni, companion mətn isə addımları, məqsədi və failure yollarını açır. Məqalələrdə həmin izahlara birbaşa keçidlər verilib.

Sxemlər üçün [diagram mərkəzi](../diagrams/README.md), laboratoriyanın tam müqaviləsi üçün [R1 sənədləri](../labs/r1/README.md), gələcək scope üçün [ROADMAP](../../ROADMAP.md) mövcuddur.
