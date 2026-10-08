# Audit, notification və əməliyyat log-u

## Sual

Eyni dəyişiklikdə niyə Activity, bildiriş və texniki log fərqli yerlərdə ola bilər?

## Sadə cavab

Audit keçmiş biznes əməliyyatını göstərir. Notification müəyyən istifadəçiyə diqqət tələb edən məlumat verir. Operational log isə texniki uğursuzluğu araşdırmaq üçündür. Audience, saxlanan məlumat və access qaydası fərqlidir.

## Cari vəziyyət

TaskFlow Activity-ni Spatie Activitylog üzərində saxlayır. Bildiriş database/in-app və Web-only-dir. Media cleanup kimi failure-lar Laravel log-u ilə qeyd edilir. R1 üçün ayrıca broker monitorinqi və event tracking platforması yoxdur.

## Terminləri sadələşdirək

**Recipient** bildirişi alan istifadəçidir. **Operational log** developer/operator-un texniki xətanı araşdırdığı qeyddir. **Safe context** araşdırmaya kömək edən, amma secret və private yol göstərməyən məlumatdır. **Sanitizer** payload-u təhlükəli/dəstəklənməyən məlumatdan təmizləyir.

Bir bağlamanın təhvil qəbzi audit-ə, alıcıya “bağlama gəldi” mesajı notification-a, avtomobil nasazlığının servis qeydi isə operational log-a bənzəyir. Auditoriya və məqsəd fərqlidir.

## Həyat ssenarisi: task dəyişir, media cleanup fail olur

1. Əvvəl task-ı Fidan və Rauf izləyir.
2. Fidan status dəyişir: Activity onun action-ını tarixçəyə yazır.
3. Recipient qaydası uyğun watcher-lərdən Fidanı çıxarır; Rauf bildiriş ala bilər.
4. Sonra Fidan attachment silir, association DB mərhələsi tamamlanır.
5. Binary cleanup fail edir: Media safe operational warning yazır.
6. Bu texniki log-u notification inbox-u kimi istifadə etmirlər; failure troubleshooting üçündür.

```text
Əvvəl: görünən task + watcher-lər
Biznes action: audit tarixi + uyğun recipient bildirişi
Texniki failure: təhlükəsiz əməliyyat log-u
Sonra: hər kanal öz məqsədinə uyğun nəticə saxlayır
```

## Real kod nümunələri

Status mutation-da iki ayrı çağırış:

```php
$this->activity->record(ActivityEvent::TaskStatusChanged, $actor, $task, [
```

```php
$this->notifications->notify($task, $actor, ActivityEvent::TaskStatusChanged);
```

Media-da texniki failure safe context ilə loglanır:

```php
Log::warning('Media physical deletion deferred.', [
    'operation' => 'delete',
    'media_id' => $media->id,
    'media_uuid' => $media->uuid,
    'uploader_id' => $media->uploaded_by,
    'error_class' => $exception::class,
]);
```

## Axın və auditoriya

```text
Biznes state change → Activity → görünürlük qaydası ilə tarixçə
Uyğun task action → eligible watcher-lər → şəxsi notification inbox
Cleanup failure → operational log → texniki araşdırma
```

Actor öz action bildirişini almır. Bu, Activity-də actor-un görünməməsi demək deyil. Notification read etmək də audit record-u silmir və task statusunu dəyişmir.

## Təhlükəsizlik sərhədi

Request dump, password, token, Authorization header və private storage path bu kanallara yazılmamalıdır. Activity sanitizer var, amma “sanitizer var deyə istənilən obyekti ötürüm” düzgün deyil. Notification linked record açılarkən access yenidən yoxlanılır.

## Kodun vacib sətirlərini açaq

- `activity->record(...)`: keçmiş dəyişiklik üçün structured audit çağırışıdır.
- `notifications->notify(...)`: bütün user-lərə yayım deyil; eligible recipient-lər seçilir.
- `Log::warning(...)`: texniki warning severity-sidir, user Activity event-i deyil.
- `'operation' => 'delete'`: hansı texniki mərhələdə problem olduğunu göstərir.
- `media_uuid`: private binary path əvəzinə təhlükəsiz identity ilə əlaqə qurmağa kömək edir.
- `'error_class' => $exception::class`: raw exception message/path dump etmədən xəta növü göstərilir.

## Niyə eyni payload-u üç yerə yazmırıq?

Notification qısa və istifadəçi məqsədli olmalıdır. Audit canonical dəyişiklik sahələrini saxlayır. Operational log troubleshooting üçün başqa context tələb edə bilər. Request-in bütün input/headers-ını bu kanallara kopyalamaq token və credential sızdıra bilər.

Actor öz action bildirişini almırsa Activity-də onun izi yenə qala bilər. Bir kanalın susması o biri kanalın işləmədiyini göstərmir.

## Konkret failure nümunəsi

Rauf notification-u əvvəl alıb, sonra layihədən çıxarılıb. Köhnə bildirişdə safe task ID qalsa da linki açanda indiki access yenidən yoxlanmalıdır. Notification-a sahib olmaq task-a qalıcı access vermir.

Cleanup warning-də `$exception->getMessage()` kor-koranə göstərilsə private storage yolu sızması riski yarana bilər. Cari nümunə error class və safe identity saxlayır; user-ə opaque failure verilir.

## Özünü yoxla

1. Notification-u read etmək task statusunu dəyişirmi? **Xeyr.**
2. Actor öz action bildirişini almırsa audit də yoxdurmu? **Xeyr; fərqli qaydalardır.**
3. Log context-inə token əlavə etmək troubleshooting üçün normaldırmı? **Xeyr.**

[Activity](../diagrams/flows/activity.md), [collaboration](../diagrams/flows/collaboration.md) və [media delete](../diagrams/flows/media-delete.md) izahları üç məqsədi ayırır.

## Kod və yoxlama

- [Status service](../../Modules/Tasks/app/Services/TaskStatusService.php)
- [Recipient və notification service](../../Modules/Tasks/app/Services/TaskWatcherNotificationService.php)
- [Media operational log-u](../../Modules/Media/app/Services/MediaStorageService.php)
- [Notification inbox testləri](../../tests/Feature/NotificationCenterTest.php)
- [Sanitizer testləri](../../Modules/Activity/tests/Unit/ActivitySanitizerTest.php)
- [Təhlükəsizlik qaydaları](../technical/SECURITY.md)
