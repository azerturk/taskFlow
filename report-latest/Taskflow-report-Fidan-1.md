14 Sentyabr — TaskFlow üzrə hesabat

🔹 Proyekt uğurla quruldumu? Qurulum zamanı hər hansı problem yaşadınızmı?
Bəli, proyekti uğurla quraşdırdım. Qurulum zamanı hər hansı problemlə qarşılaşmadım.

🔹 Proyekti manual olaraq UI-da test etdinizmi? Hər şey işləyirmi?
Bəli, proyekti UI üzərindən manual olaraq test etdim. Ümumi funksionallıq öz qaydasında işləyir.

🔹 Proyekti browser-də manual test edərkən hər hansı problemlə qarşılaşdınızmı?
Bəli, bir problem müşahidə etdim. Project key ilə bağlı hissədə PAY-42 kimi dəyər daxil etdikdə sistem bunu gözlədiyim formada qəbul etmir. Problemin səbəbini anlamaq üçün həmin hissənin kodunu da nəzərdən keçirdim. Manual test zamanı qarşılaşdığım əsas problem bu oldu.

🔹 Testləri özünüz və ya Codex vasitəsilə işə saldınızmı?
Bəli, testləri işə saldım. Codex vasitəsilə ümumi test yoxlaması apardım. Bundan əlavə, test fayllarını özüm də oxuyub nəzərdən keçirdikdən sonra testləri icra etdim.

🔹 Codex + Playwright ilə avtomatik testlər etdinizmi?
Bəli. Codex üçün uyğun prompt hazırladım və Playwright vasitəsilə avtomatik browser testlərini icra etdim.

🔹 Bütün dokumentasiyaları oxudunuzmu?
Bəli, dokumentasiyaları nəzərdən keçirdim və layihənin strukturu, biznes qaydaları və mövcud funksionallıqları ilə tanış oldum.

🔹 Codex vasitəsilə kod bazasını araşdırdınızmı? Başa düşmədiyiniz hissələr üçün Codex-dən açıqlama istədinizmi?
Bəli, Codex vasitəsilə kod bazasını araşdırdım. Xüsusilə təhlükəsizliklə bağlı fayllarda anlamaqda çətinlik çəkdiyim hissələr oldu. Bu hissələrin nə üçün istifadə edildiyini və necə işlədiyini daha yaxşı başa düşmək üçün Codex-dən əlavə izahlar tələb etdim.

🔹 Hər hansı səhv/bug tapdınızmı? Tapdığınız problemləri fix edib səbəbini öyrənməyə çalışdınızmı?
Bəli. Codex vasitəsilə layihənin ümumi biznes qaydalarını yoxlayarkən bir potensial problem aşkar edildi: suspend edilmiş istifadəçinin bəzi Livewire komponentlərini render edə bilməsi. Bu vəziyyət istifadəçi suspend edildikdən sonra onun sistemdəki giriş imkanlarının tam məhdudlaşdırılması qaydası ilə ziddiyyət təşkil edə bilər.

Bu problemi araşdırdım, lakin hazırda kod bazasında bununla bağlı dəyişiklik və ya fix etməmişəm.

Aşkar etdiyim problemlər

1. Project key problemi: PAY-42 kimi dəyərin daxil edilməsi zamanı sistem gözlənilən davranışı göstərmir. Problemi anlamaq üçün əlaqəli kod hissələrini nəzərdən keçirdim.
2. Suspend edilmiş istifadəçi problemi: Codex ilə biznes qaydalarını yoxlayarkən suspend edilmiş istifadəçinin Livewire komponentlərini render edə bildiyi müəyyən edildi. Bu, authorization/security baxımından əlavə yoxlama tələb edən məsələdir. Hazırda bu problemə dəyişiklik edilməyib.