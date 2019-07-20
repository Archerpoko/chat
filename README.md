# Aplikacja Chatu napisana przez Jakuba Pokornego

Napisana została z wykorzystanie frameworka symfony w wersji 4.3.2 języka PHP biblioteki jQuery oraz Bootstrap.
Aplikacja ma być prezentacją wiedzy z zakresu technologii HTML CSS JS oraz PHP.
Aplikacja jest stabilna i spełnia swoją główną rolę choć nadal zamierzam ją rozwijać, wszelkie planowane rozbudowy znajdują się w pliku todo.txt

## Możliwości
* Możliwośc rejestracji/logowania
* Możliwośc zmiany dodania i wyświetlenia avataru imienia oraz nazwiska
* Obsługa znajomych w postaci dodawania i usuwania
* Możliwość prowadzenia bezpośrednich rozmów ze znajomymi

## Instalacja
* W celu uruchomienia aplikacji niezbędne jest poprawnie skonfigurowany Daemon http o których konfiguracji można przeczytać [tutaj](https://symfony.com/doc/current/setup/web_server_configuration.html)
* Ponieważ w projekcie wykorzystywane są ścieżki do zasobów rozpoczynających się od "/" należy dodać również w pliku host przekierowanie do folderu public/
* Aby dokończyć poprawne konfigurowanie środowiska należy dokonać ostatnich kroków które znajdziemy [tutaj](https://symfony.com/doc/current/setup.html#setting-up-an-existing-symfony-project)
* Ostatnią kwestią jest podanie danych do serwera mysql w pliku .env oraz wykonanie migracji przy użyciu doctrine co pozwoli na komunikacje z bazą

