;<? exit(); ?>


[database]

;Сервер базы данных
db_server = "localhost"

;Пользователь базы данных
db_user = "root"

;Пароль к базе
db_password = "root"

;Имя базы
db_name = "new_nebo_db"

;Префикс для таблиц
db_prefix = s_;

;Кодировка базы данных
db_charset = UTF8;

;Режим SQL
db_sql_mode =;

;Смещение часового пояса
;db_timezone = +04:00;


[php]
error_reporting = E_ALL;
php_charset = UTF8;
php_locale_collate = ru_RU;
php_locale_ctype = ru_RU;
php_locale_monetary = ru_RU;
php_locale_numeric = ru_RU;
php_locale_time = ru_RU;
;php_timezone = Europe/Moscow;

logfile = admin/log/log.txt;

[smarty]

smarty_compile_check = true;
smarty_caching = false;
smarty_cache_lifetime = 0;
smarty_debugging = false;
smarty_html_minify = false;
 
[images]
;Использовать imagemagick для обработки изображений (вместо gd)
use_imagick = true;

;Директория оригиналов изображений
original_images_dir = files/products_originals/;

;Директория миниатюр
resized_images_dir = files/products/;

;Изображения категорий
categories_images_dir = files/categories_originals/;
resized_categories_images_dir = files/categories/;

;Изображения постов блога
blog_images_dir = files/blog_originals/;
resized_blog_images_dir = files/blog/;

;Изображения брендов
brands_images_dir = files/brands_originals/;
resized_brands_images_dir = files/brands/;

;Изображения страниц
pages_images_dir = files/pages_originals/;
resized_pages_images_dir = files/pages/;
pages_bg_images_dir = files/pages_bg/;

;Изображения выдач
loans_images_dir = files/loans_originals/;
resized_loans_images_dir = files/loans/;

;Галереи
galleries_images_dir = files/galleries_originals/;
resized_galleries_images_dir = files/galleries/;

;Изображения тегов блога
blog_tags_images_dir = files/blog_tags_originals/;
resized_blog_tags_images_dir = files/blog_tags/;

;Изображения авторов
authors_images_dir = files/authors_originals/;
resized_authors_images_dir = files/authors/;

; Inventories (Restocking)
inventories_images_dir = files/inventories/;

; Временные файлы
tmp_dir = files/tmp/;

;Файл изображения с водяным знаком
watermark_file = backend/files/watermark/watermark.png;

[files]

;Директория хранения цифровых товаров
downloads_dir = files/downloads/;

download_dir = files/download/;