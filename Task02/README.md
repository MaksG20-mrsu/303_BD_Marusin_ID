# Лабораторная работа 2

Скрипт `db_init.bat` создаёт базу данных SQLite `movies_rating.db` и заполняет её данными из файлов `movies.csv`, `ratings.csv`, `tags.csv` и `users.txt`.

## Требования к окружению
- Python 3, доступный командой `python3` (используются только стандартные модули);
- SQLite 3, доступный командой `sqlite3`;
- командная оболочка bash (Linux, macOS; в Windows — Git Bash или WSL).

## Запуск
Из каталога `Task02`:
```
./db_init.bat
```
1. `make_db_init.py` читает файлы с данными и создаёт SQL-скрипт `db_init.sql`: удаление и создание таблиц `movies`, `ratings`, `tags`, `users` и команды `INSERT` для загрузки данных.
2. `sqlite3` выполняет `db_init.sql` для базы `movies_rating.db`.

Если таблицы в базе уже есть, они удаляются и создаются заново.
