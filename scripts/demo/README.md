# FixPoint — демонстрационный запуск

Один файл запускает всё, что нужно для показа проекта:

- портативный MySQL на `127.0.0.1:3307`;
- сайт FixPoint на `http://127.0.0.1:8095`;
- Adminer для просмотра и редактирования БД на `http://127.0.0.1:8096`.

## Быстрый старт

Дважды щёлкните `start-demo.cmd` в корне проекта или выполните в терминале:

```powershell
.\start-demo.cmd
```

Скрипт сам:

1. поднимет MySQL, если порт `3307` свободен;
2. создаст базу `fixpoint_demo` и аккаунт `fixpoint_viewer` со всеми правами;
3. применит миграции и демо-seed (14 пользователей, 7 категорий, 100 услуг, 24 заявки);
4. развернёт код из ветки каталога PR #26 во временную папку и запустит сайт;
5. запустит Adminer и откроет сайт и БД в браузере.

Рабочее дерево, `main` и `develop` не изменяются: исходники демо-сайта копируются в
`%LOCALAPPDATA%\FixPointDemo\app`.

## Доступы

| Что | Значение |
|---|---|
| Сайт | `http://127.0.0.1:8095` |
| Adminer | `http://127.0.0.1:8096` |
| MySQL | `127.0.0.1:3307` |
| База | `fixpoint_demo` |
| Логин | `fixpoint_viewer` |
| Пароль | `FixPoint!View1` |
| Права | `ALL PRIVILEGES` на `fixpoint`, `fixpoint_demo`, `fixpoint_test` |

В форме входа Adminer сервер и база уже подставлены — останется ввести логин и пароль.
Язык интерфейса переключается внизу страницы входа: `English` → `Русский`.

Пароль локальный, база слушает только `127.0.0.1`. Для production этот аккаунт не использовать.

## Управление

```powershell
.\start-demo.cmd -Status          # состояние портов и процессов
.\start-demo.cmd -Stop            # остановить сайт и Adminer (MySQL — если его запустил скрипт)
.\start-demo.cmd -NoBrowser       # не открывать браузер
.\start-demo.cmd -Reseed          # принудительно повторить seed
.\start-demo.cmd -SkipRefresh     # не пересобирать исходники демо-сайта
.\start-demo.cmd -AppPort 8097 -AdminerPort 8098
```

Обычный `Ctrl+C` в окне терминала **не останавливает** сайт и БД: они запущены отдельными
процессами. Для остановки используйте `-Stop`.

## Где что лежит

- исходники демо-сайта: `%LOCALAPPDATA%\FixPointDemo\app`;
- Adminer: `%LOCALAPPDATA%\FixPointDemo\adminer`;
- логи: `%LOCALAPPDATA%\FixPointDemo\logs`;
- состояние и PID: `%LOCALAPPDATA%\FixPointDemo\demo.json`.

## Если что-то не запустилось

- **Порт занят.** Закройте старый процесс или укажите другие порты через `-AppPort` / `-AdminerPort`.
- **MySQL не поднимается.** Проверьте лог `%LOCALAPPDATA%\FixPointDemo\logs\mysql.log` и что папка
  `C:\Users\Public\fixpoint-mysql` существует. Другой путь можно задать переменной
  `FIXPOINT_MYSQL_DIR`.
- **Нет PHP.** Добавьте `C:\php` в `PATH` или установите PHP 8.3+.
- **Нет Adminer.** Скрипт скачивает его при первом запуске; без интернета сайт всё равно
  запустится, а Adminer можно положить вручную в `%LOCALAPPDATA%\FixPointDemo\adminer\adminer.php`.
- **Демо-данные испорчены.** Запустите `.\start-demo.cmd -Reseed`; для полного сброса удалите
  базу `fixpoint_demo` и запустите снова.

## Требования

Windows 10/11, PHP 8.3+ в `PATH`, Git, `tar` (входит в Windows 10+), портативный MySQL
в `C:\Users\Public\fixpoint-mysql` (или `FIXPOINT_MYSQL_DIR`).
