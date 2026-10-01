# 14 Sentyabr — TaskFlow üzrə hesabat

🔹 **Proyekt uğurla quruldumu? Qurulum zamanı hər hansı problem yaşadınızmı?**
Bəli, proyekti uğurla quraşdırdım. Qurulum zamanı bəzi kiçik warning-lər oldu, lakin onlar layihənin işləməsinə mane olmadı.

🔹 **Proyekti manual olaraq UI-da test etdinizmi? Hər şey işləyirmi?**
Bəli, proyekti UI üzərindən manual olaraq test etdim. Login, user management, project və task yaradılması, task workflow və digər əsas funksionallıqlar düzgün işləyir.

🔹 **Proyekti browser-də manual test edərkən hər hansı problemlə qarşılaşdınızmı?**
Bəli. `Project Key` sahəsində lowercase `pay` daxil etdikdə vizual olaraq `PAY` görünsə də, sistem validation xətası verir. Dəyəri birbaşa uppercase `PAY` olaraq daxil etdikdə isə düzgün işləyir.

🔹 **Testləri özünüz və ya Codex vasitəsilə işə saldınızmı?**
Bəli, testləri işə saldım. Bundan əlavə, Codex vasitəsilə test strukturu, PHPUnit/Pest testləri, MySQL test mühiti və testlərin işləmə prinsipi araşdırıldı. Testlər uğurla tamamlandı.

🔹 **Codex + Playwright ilə avtomatik testlər etdinizmi?**
Bəli. Playwright testlərinin strukturu və yoxladığı ssenarilər Codex vasitəsilə analiz edildi. Daha sonra avtomatik browser testləri işə salındı və bütün testlər uğurla tamamlandı.

🔹 **Bütün dokumentasiyaları oxudunuzmu?**
Bəli, bütün dokumentasiyaları nəzərdən keçirdim və layihənin strukturu, biznes qaydaları, modulları, təhlükəsizlik və mövcud funksionallıqları ilə tanış oldum.

🔹 **Codex vasitəsilə kod bazasını araşdırdınızmı? Başa düşmədiyiniz hissələr üçün Codex-dən açıqlama istədinizmi?**
Bəli, Codex vasitəsilə kod bazasını araşdırdım. Xüsusilə authorization, Policy/Gate, Sanctum abilities, Livewire komponentləri, test arxitekturası və biznes qaydalarının necə yoxlanıldığını daha yaxşı başa düşmək üçün əlavə izahlar aldım.

🔹 **Hər hansı səhv/bug tapdınızmı? Tapdığınız problemləri fix edib səbəbini öyrənməyə çalışdınızmı?**
Bəli. `Project Key` sahəsində UX/validation problemi aşkar etdim. Problemi kod səviyyəsində araşdırdım və müəyyən etdim ki, `uppercase` CSS yalnız görünüşü dəyişir, input-un real dəyərini uppercase etmir. Buna görə lowercase dəyər validation-dan keçmir. Hazırda kodda dəyişiklik edilməyib.

### Aşkar etdiyim problem

1. **Project Key problemi:** `pay` daxil edildikdə vizual olaraq `PAY` görünür, lakin real dəyər lowercase qaldığı üçün validation xətası yaranır. `PAY` birbaşa böyük hərflərlə daxil edildikdə düzgün işləyir.