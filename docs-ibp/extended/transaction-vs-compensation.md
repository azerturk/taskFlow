# Transaction və compensation

## Sual

DB rollback etdikdə diskə yüklənmiş fayl da silinir?

## Sadə cavab

Xeyr. Lokal DB transaction yalnız həmin DB connection-da iştirak edən yazıları geri alır. Fayl storage əməliyyatı eyni transaction-un hissəsi deyil. Ona görə edilmiş xarici işi əks əməliyyatla təmizləmək — compensation — ayrıca lazımdır. Pattern-in ümumi mənası üçün [Microsoft-un rəsmi compensation izahına](https://learn.microsoft.com/en-us/azure/architecture/patterns/compensating-transaction) bax; aşağıdakı TaskFlow davranışı isə öz kodumuzla təsdiqlənir, ayrıca saga platforması implementasiya edildiyini demir.

## Cari vəziyyət

TaskFlow media batch-də əvvəl bütün faylları yoxlayır, sonra storage yazır, sonra metadata/association/Activity-ni DB transaction-da yaradır. Uğursuzluqda saxlanmış fayllar ledger üzrə kompensasiya edilməyə çalışılır; cleanup özü də fail ola bilər. R1 rebuild isə yalnız DB yazılarını bir transaction-a yığır.

## Terminləri sadələşdirək

**Atomic/atomik** bir write qrupunun ya birlikdə qalması, ya da geri alınmasıdır. **Compensation/kompensasiya** artıq edilmiş işi əks əməliyyatla təmizləməyə cəhddir. **Ledger** burada hansı faylların uğurla yazıldığını saxlayan siyahıdır. DB ilə disk fərqli resurslardır.

Toy üçün zal və yemək sifarişini düşün: zal bronu ləğv olundu deyə yemək sifarişi avtomatik ləğv edilmir. Onu ayrıca ləğv etməlisən; hətta ikinci ləğv zəngi də alınmaya bilər. Storage cleanup üçün də ayrıca failure düşünülür.

## Həyat ssenarisi: iki fayldan sonra DB fail olur

1. Əvvəl Task attachment request-də A.png və B.pdf gəlir.
2. Media hər ikisini əvvəl validate edir; invalid fayl varsa write başlamır.
3. İkisi private diskə yazılır və stored-items siyahısı hazırlanır.
4. Metadata/association/Activity üçün DB transaction açılır.
5. İkinci association write fail edir: DB transaction rollback olur.
6. Diskdəki A və B öz-özünə silinmir; `compensateStored` ledger üzrə cleanup edir.
7. Cleanup alınmasa pending vəziyyət və safe log/metadata cəhdi qalır.

```text
Əvvəl: fayl/row yoxdur
Aralıq: diskdə fayllar var, DB transaction açıqdır
DB failure: row-lar rollback → fayllar üçün ayrıca cleanup
Sonra: cleanup tamamlanıb və ya açıq pending-cleanup failure var
```

## Real kod

Task attachment flow-un failure hissəsi:

```php
} catch (Throwable $e) {
    $this->storage->compensateStored($actor, $stored, $e);
    throw $e;
}
```

Storage kompensasiyası saxlanmış faylları tərs ardıcıllıqla təmizləyir:

```php
foreach (array_reverse($stored) as $item) {
    try {
        $this->deletePhysical($item->disk, $item->path);
```

Bu, tam metod deyil; cleanup failure, retained metadata və təhlükəsiz log hissələri orijinal source-dadır.

## Axın

```text
Bütün faylları validate → storage A/B → DB metadata + associations + Activity
DB write fail → DB rollback → storage A/B cleanup
Cleanup fail → retry üçün safe metadata saxlama cəhdi + opaque exception/log
```

“All-or-nothing” niyyəti fiziki storage outage-ın mövcud olmadığını demir. Cleanup da fail ola bilər. Kod bunu gizlətmir: safe UUID-lər və məqsədli failure saxlayır, credential/private path-i user-ə göstərmir. Retention yazısı da fail olsa operational log kritik vəziyyəti qeyd edir.

## Delete ayrıca axındır

Attachment association/Activity əvvəl DB-də commit edilir, sonra Media fiziki cleanup edir. Fiziki silmə fail olsa metadata aktiv və unassociated qala bilər. Upload compensation ilə delete flow-u eyni transaction sxemi kimi təsvir etmə.

## Kod parçasını açaq

- `catch (Throwable $e)`: DB mərhələsinin uğursuzluğu cleanup yolunu işə salır.
- `$stored`: bu request-in uğurlu fiziki write-larını bildirir; bütün storage-u silmək siyahısı deyil.
- `compensateStored(...)`: yalnız həmin ledger üzrə təmizləyir.
- `array_reverse($stored)`: cleanup yazıların tərs ardıcıllığı ilə cəhd edilir.
- `deletePhysical(...)`: DB rollback deyil, real storage əməliyyatıdır.
- `throw $e`: ilk failure gizlədilərək uğur cavabı verilmir; cleanup özü failure atarsa pending failure da görünə bilər.

## Niyə “transaction-a saldım, bəsdir” deyil?

DB transaction storage driver-in içini idarə etmir. Write-ların hamısı bir PHP metodunda olmaq onların vahid atomik resurs olması demək deyil. Ledger hər failure nöqtəsində hansı fiziki nəticənin qaldığını bilməyə kömək edir.

Bu fərq delete-də də görünür: association commit-dən sonra binary cleanup edilir. Fiziki delete failure association-u geri gətirmir.

## Konkret recovery məhdudiyyəti

Fiziki silmə alınmırsa Media aktiv, əlaqəsiz metadata saxlayıb safe UUID ilə pending failure bildirə bilər. Bu avtomatik worker-in cleanup edəcəyi zəmanəti deyil. Hətta həmin metadata write-ı da fail ola bilər; critical operational log bunun üçün var.

Association silinmiş delete request-ini eyni endpoint ilə təkrar göndərmək safe 404 verə bilər. Test internal `MediaStorageService::delete($media)` retry-sini sübut edir, mövcud olmayan admin cleanup UI-sini deyil.

## Özünü yoxla

1. DB rollback disk faylını silirmi? **Xeyr.**
2. Pending metadata avtomatik retry deməkdirmi? **Xeyr.**
3. Compensation heç vaxt fail olmurmu? **Fail ola bilər; həmin vəziyyət ayrıca saxlanmalı/görünməlidir.**

[Upload izahı](../diagrams/flows/media-upload.md) ilə [delete izahını](../diagrams/flows/media-delete.md) müqayisə et: transaction sərhədləri eyni deyil.

## Kod və yoxlama

- [Attachment use case](../../Modules/Tasks/app/Services/TaskAttachmentService.php)
- [Storage və compensation](../../Modules/Media/app/Services/MediaStorageService.php)
- [Failure safety testləri](../../Modules/Tasks/tests/Feature/TaskAttachmentFailureSafetyTest.php)
- [Media müqaviləsi](../modules/MEDIA.md)
- [After-commit ilə fərq](after-commit.md)
