# DTO, model və Resource fərqlidir

## Sual

Niyə request array-ni birbaşa modelə ötürmürük və modeli birbaşa JSON qaytarmırıq?

## Sadə cavab

DTO əməliyyatın hansı input-u qəbul etdiyini göstərir. Model persistence obyektidir. Resource isə client-in hansı sahələri görəcəyini seçir. Bu üç məqsədi bir obyektə yığmaq server-owned və private sahələrin təsadüfən yazılması/göstərilməsi riskini artırır.

## Cari vəziyyət

Məhsul mutation-ları purpose-specific readonly DTO istifadə edir. API response explicit Resource-dur. R1 public feed-i internal model əvəzinə readonly read DTO qaytarır.

## Terminləri sadələşdirək

**DTO — Data Transfer Object** məlumatı məqsədli sərhəddən keçirmək üçündür. **Readonly** onun qurulduqdan sonra sahələrini yenidən təyin etməmək qaydasıdır. **Model** DB record-u və persistence/relation imkanlarını daşıyır. **Resource** isə HTTP response üçün görünən sahələri seçir.

Sifariş forması, anbardakı mal kartı və müştəriyə verilən qəbz eyni sənəd deyil. Formada istənilən dəyişiklik yazılır; kartda daxili məlumat var; qəbzdə müştəri üçün lazım olan hissə göstərilir. DTO/model/Resource məqsəd ayrılığı da buna bənzəyir.

## Həyat ssenarisi: iki versiya dəyəri

1. Əvvəl browser task-ı oxuyur və server `version=3` qaytarır.
2. Browser status request-də `expected_version=3` göndərir.
3. FormRequest shape-ni yoxlayır, DTO bunu `expectedVersion` kimi daşıyır.
4. Service modelin DB-dən gələn cari `version`-u ilə müqayisə edir.
5. Uğurlu mutation modelin version-unu artırır.
6. Resource yeni `version`-u response-a daxil edir; input DTO dəyişdirilmir.

```text
Əvvəl: client-in bildiyi version
Input: DTO.expectedVersion
Persistence: Task.version
Output: Resource-un seçdiyi version
```

## Real kod

Status input DTO-su:

```php
final readonly class ChangeTaskStatusData
{
    public function __construct(public TaskStatus $status, public int $expectedVersion) {}

    public static function fromArray(array $data): self
    {
        return new self(TaskStatus::from($data['status']), (int) $data['expected_version']);
    }
}
```

Resource isə response sahələrini ayrıca seçir:

```php
'version' => $this->version,
'rank' => $this->rank,
'status' => $this->status->value,
```

`Task` modelinin bütün metod/relation/attribute-ları bu response müqaviləsi deyil. DTO-dakı `expectedVersion` də modelin cari `version`-u deyil, caller-in oxuduğu versiyadır.

## Axın

```text
Validated request → ChangeTaskStatusData → service → persisted Task → TaskResource → JSON
Catalog model → PublishedLearningEntryData → başqa modulun oxusu
```

## Yanlış nəticə

Readonly DTO input-un HTTP-də validasiya olunduğunu öz-özünə sübut etmir. `ChangeTaskStatusData::fromArray()` validated array gözləyir. R1 title DTO-su isə HTTP girişi olmadığı üçün constructor-da boş/uzun title-ı ayrıca rədd edir.

Resource query yeri deyil: relation artıq repository tərəfindən hazırlanmalı, optional relation `whenLoaded()` ilə göstərilməlidir. Hər qatın məqsədini saxla; bütün sistem üçün bir universal DTO düzəltmə.

## Kodun vacib hissələrini açaq

- `final readonly class`: input obyekti məqsədli və sonradan dəyişdirilməyən formadır.
- `TaskStatus $status`: istənilən string deyil, enum dəyəridir.
- `int $expectedVersion`: caller-in gözləntisidir, serverin yeni versiyasını seçməsi deyil.
- `TaskStatus::from(...)`: validated status enum-a çevrilir; yanlış input-u kor-koranə ötürmək olmaz.
- Resource-da `'status' => $this->status->value`: enum HTTP üçün string dəyərinə çevrilir.

Readonly olmaq obyektin bütün biznes cəhətdən doğru olduğunu sübut etmir. Məsələn, DTO-da `Done` var deyə açıq subtask olan parent tamamlanmır; həmin state qaydası service-dədir.

## Niyə eyni obyekti hər yerə vermirik?

Modelin bütün sahələri response-a açıq deyil. Request-in bütün sahələri də modelə yazıla bilməz: reporter, raw rank və issue sequence server tərəfindən idarə edilir. Ayrı obyektlər bu sərhədləri oxunaqlı edir.

R1 read DTO-su da consumer-in Catalog modelini dəyişməsinə və relation query-sinə ehtiyac yaratmır. Consumer yalnız razılaşdırılmış məlumatı alır.

## Konkret failure nümunəsi

Client əlavə `creator_id` göndərir. `request()->all()` birbaşa persistence-ə ötürülsə client server-owned sahəni seçməyə çalışa bilər. Purpose-specific DTO və explicit service/model write bu təsadüfi genişlənmənin qarşısını almağa kömək edir.

Resource daxilində relation-i lazy oxumaq da nəticə göstərməyi query əməliyyatına çevirər. Düzgün prepared relation yoxdursa problem repository/query nəticəsində həll edilməlidir.

## Özünü yoxla

1. DTO `save()` edilən DB modeli varmı? **Xeyr.**
2. Modelin bütün attribute-ları API müqaviləsidirmi? **Xeyr; Resource explicit sahələri seçir.**
3. Readonly DTO authorization verirmi? **Xeyr; access ayrıca yoxlanılır.**

[Request qatlarının izahı](../diagrams/system/request-layers.md) input və output sərhədlərini, [public feed](../diagrams/labs/public-feed.md) read DTO-nu göstərir.

## Kod və yoxlama

- [Status DTO](../../Modules/Tasks/app/Data/ChangeTaskStatusData.php)
- [Create DTO](../../Modules/Tasks/app/Data/CreateTaskData.php)
- [Task Resource](../../Modules/Tasks/app/Http/Resources/TaskResource.php)
- [R1 input DTO](../../Modules/LearningCatalog/app/Data/PublishLearningEntryData.php)
- [R1 read DTO](../../Modules/LearningCatalog/app/Data/PublishedLearningEntryData.php)
- [Input/output təhlükəsizliyi](../technical/SECURITY.md)
