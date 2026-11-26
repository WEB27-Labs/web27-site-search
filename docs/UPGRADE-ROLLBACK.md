# Upgrade and rollback / Обновление и откат

## EN

1. Back up the database and exact installed plugin directory; use a staging copy. Record index/vector counts and active provider without exposing credentials.
2. Install the FULL package by replacing the existing plugin. The canonical folder is `web27-site-search/`; a legacy installation under a different folder name needs a separately planned migration to avoid a second active copy. Do not uninstall to update.
3. Version 1.0.0 is the first public edition. A private test build may have a higher numerical version; WordPress or an updater may identify this replacement as a downgrade. Do not uninstall, and do not enable two copies. The database schema markers are unchanged. No reindex is required solely for the version-label change; verify on staging before replacing a test build.
4. Check saved settings and keys, source selection, indices, cron, local search, chat, semantic search and both locales. A provider/endpoint/embedding model switch is distinct from an update and can require new vectors.
5. Roll back by replacing files with the backed-up version. If needed, restore the protected database snapshot after accounting for new data. Uninstall deliberately deletes plugin data and matching stored temporary records; object-cache-only records may expire later. Do not use uninstall as rollback.

## RU

1. Создайте резервную копию БД и точной папки установленного плагина; используйте тестовую копию. Зафиксируйте счётчики индекса/векторов и активного провайдера без раскрытия ключей.
2. Установите FULL заменой существующей версии. Каноническая папка `web27-site-search/`; при старой нестандартной папке отдельно согласуйте перенос, чтобы не создать две активные копии. Не удаляйте плагин при обновлении.
3. Версия 1.0.0 — первая публичная редакция. У приватной тестовой сборки номер может быть выше, поэтому WordPress или менеджер обновлений может считать замену понижением версии. Не удаляйте плагин и не активируйте две копии. Версии схем БД не изменены. Переиндексация только из-за смены номера не нужна; проверьте замену на тестовой копии.
4. Проверьте настройки и ключи, выбранные источники, индексы, cron, локальный поиск, чат, эмбеддинги и оба языка. Смена провайдера/адреса/модели — отдельная операция, которая может потребовать новых векторов.
5. Для отката восстановите файлы исходной версии; при необходимости — защищённую копию БД с учётом новых данных. Uninstall намеренно удаляет данные плагина и соответствующие временные записи в БД; записи только в объектном кэше могут сохраняться до истечения срока. Не используйте удаление как откат.
