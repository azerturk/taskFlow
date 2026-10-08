# Modular monolith və microservice

## Sual

İki modul və event varsa artıq microservice arxitekturasıdır?

## Sadə cavab

Xeyr. Modul kod və məsuliyyət sərhədidir. Microservice yanaşmasında ayrıca işləmə/deploy sərhədi və şəbəkə üzərindən əlaqə kimi əlavə əməliyyat problemləri yaranır. Event istifadə etmək təkbaşına deployment modelini dəyişmir.

## Cari vəziyyət

TaskFlow vahid Laravel tətbiqi, vahid database və vahid deploy vahididir. Beş məhsul modulu və onlardan izolə edilmiş iki R1 tədris modulu var. R1 listener-i eyni PHP prosesində sinxron işləyir.

## Terminləri sadələşdirək

**Monolith** birlikdə işləyən/deploy olunan vahid tətbiqdir. **Modular** həmin tətbiqin daxilində məsuliyyət və dependency sərhədlərinin olmasıdır. **Deploy** kodun işləyən mühitə yerləşdirilməsidir. **Network failure** isə ayrı proseslər arasında əlaqənin timeout/qopması kimi problemdir.

Bir binada ayrı şöbələr modular monolith-ə bənzəyir: öz məsuliyyətləri var, amma eyni bina və infrastrukturdadırlar. Ayrı ünvanlardakı şirkətlər arasında işləmək isə əlavə nəqliyyat/rabitə razılaşmaları tələb edir. Bu bənzətmə microservice-in bütün xüsusiyyətlərini əhatə etmir, yalnız sərhəd fərqini göstərir.

## Həyat ssenarisi: eyni tətbiqdə iki yeni modul

1. Əvvəl TaskFlow vahid Laravel tətbiqi kimi boot olur.
2. Məhsul və lab provider-ləri həmin container-də qeydiyyatdan keçir.
3. Insights feed interface-ni constructor ilə istəyir.
4. Container Catalog-un real implementasiyasını verir.
5. `all()` eyni PHP prosesində çağırılır və eyni DB-dən Catalog repository-si oxuyur.
6. Sonra Insights öz cədvəlinə yazır; ayrıca HTTP service və ayrıca deployment baş vermir.

```text
Əvvəl: bir tətbiq/container/DB
Əlaqə: typed lokal PHP call və lokal event
Sonra: eyni deploy daxilində ayrı responsibility/ownership
```

## Real kod

Insights public contract-ı constructor ilə alır:

```php
public function __construct(
    private readonly PublishedLearningEntryFeed $entries,
    private readonly LearningInsightRepositoryInterface $insights,
) {}
```

`$this->entries->all()` lokal PHP çağırışıdır. HTTP client, network timeout və remote service discovery yoxdur. Cədvəllər eyni DB-dədir, amma hər R1 modulunun öz cədvəl sahibliyi var.

## Müqayisə

| Mövzu | Hazırkı TaskFlow | Ayrı microservice-də düşünüləcək məsələ |
|---|---|---|
| Deploy | Bir tətbiq | Xidmətlərin ayrıca deploy/uyğunluğu |
| Call | Lokal PHP və native event | Şəbəkə protokolu, timeout, retry |
| DB | Vahid DB, məqsədli ownership | Xidmətin məlumat sahibliyi və cross-service consistency |
| Failure | Eyni proses, lokal DB transaction sərhədi | Qismən şəbəkə failure və recovery |

Sağ sütun anlayış müqayisəsidir; TaskFlow üçün yeni implementasiya planı deyil.

## Axın

```text
TaskFlow deploy → Laravel container → module provider-lər → lokal service/repository əlaqələri
R1 publish → lokal commit → lokal dispatcher → lokal Insights repository
```

## Yanlış yanaşma

Ayrı folder yaratmaq loose coupling-i avtomatik vermir. Başqa modulun internal model/repository-sinə hər yerdən giriş varsa folder sərhədi zəifdir. Public contract, table ownership və architecture testləri bu səbəbdən öyrənilir.

Əksinə, hazırkı explicit production dependency-ləri gizlətmək üçün broker/event platforması əlavə etmək də bu praktikanın məqsədi deyil. Kiçik laboratoriya anlayışları öyrədir, production modullarını avtomatik refaktor etmir.

## Kodun vacib hissələrini açaq

- `PublishedLearningEntryFeed $entries`: consumer concrete Catalog repository-si yox, public müqavilə istəyir.
- `LearningInsightRepositoryInterface $insights`: öz write sərhədini ayrıca alır.
- Constructor injection: dependency-lər gizli global çağırış kimi yox, class-ın ehtiyacı kimi görünür.
- `$entries->all()`: lokal method call-dır; HTTP status və network response parse edilmir.

Bu ayrılıq class səviyyəsində dependency-ni aydın edir. Obyektlərin eyni prosesdə işləməsi isə transaction/failure qaydalarını yenə düşünməyi tələb edir.

## Niyə dərhal microservice etmirik?

Ayrı xidmət daha çox əməliyyat məsuliyyəti gətirir: network timeout, müqavilə uyğunluğu, deploy koordinasiyası və müşahidə. Cari lab bu problemləri həll etmiş kimi göstərilmir. Sadə local requirement üçün əlavə platforma yaratmaq bu məşqin məqsədi deyil.

Modular monolith də “istənilən modul istənilən cədvələ getsin” demək deyil. R1 cədvəl sahibliyi və exact public type sərhədləri bu səbəbdən qorunur.

## Konkret yanlış gözlənti

Junior event istifadə edildiyi üçün listener-in ayrıca serverdə işlədiyini zənn edir. Cari listener eyni PHP prosesindədir; yavaşdırsa caller gözləyir, fail olsa exception yayılır. Deployment forması event sözü ilə dəyişmir.

Digər yanlışlıq Catalog folder-ini başqa repo-ya köçürüb qalan contract-ın avtomatik işləyəcəyini düşünməkdir. Lokal PHP call-ı remote protokola çevirmək ayrıca dizayn və müqavilə/failure testləri tələb edər.

## Özünü yoxla

1. İki folder iki microservice deməkdirmi? **Xeyr.**
2. Event mütləq başqa prosesdə işləyirmi? **Xeyr.**
3. Eyni DB ownership sərhədini ləğv edirmi? **Xeyr; R1 başqa modulun cədvəlinə direct query-ni qadağan edir.**

[Sistem konteksti](../diagrams/system/context.md) və [dependency xəritəsi](../diagrams/system/dependencies.md) mövcud deployment ilə kod sərhədini göstərir.

## Kod və yoxlama

- [Cari arxitektura](../technical/ARCHITECTURE.md)
- [Rebuild constructor və lokal call](../../Modules/LearningInsights/app/Services/RebuildLearningInsightsService.php)
- [Modul metadata-sı](../../Modules/LearningInsights/module.json)
- [Dependency sərhədi testləri](../../tests/Architecture/R1LearningBoundaryTest.php)
- [Direct call və event](direct-call-vs-event.md)
