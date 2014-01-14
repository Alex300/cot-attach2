# Attachments 2 for Cotonti

With this plugin you can attach files and images to any Cotonti objects including pages, forum posts and organize them into galleries or downloads.

Верся 2.1.7

Что нового:
- JavaScript-Load-Image обновлен до последней версии

- Bootstrap обновлен до последней версии

- Добавлена возможность загружать файлы по частям для обхода ограничения размера файла в POST запросах

- Добавлена возможность уменьшать загружаемые изображения до заданных размеров, чтобы не хранить на сервере без надобности большие файлы

- Добавлена возможность ставить водяной знак на миниатюры, если их размеры превышают заданные.

- Добавлена возможность добавлять одному элементу несколько наборов вложений. Например у пользователя может быть аватар и файлы.

- Добавлена возможность встраивать блок добавления файлов прямо на страницу без использования iFrame. Что удобно, например при редактировании страницы.
Для этого используется калбек функция для шаблонов:

/**
 * Generates a form input file
 * Use it as CoTemplate callback.
 *
 * @param $area	
 * @param $item
 * @param string $name Input name
 * @param string $type File types. Comma separated 'all', 'file', 'image', 'audio', 'video'
 * @param int $limit file limit
 *      -1 - use plugin config value
 *       0 - unlimited
 * @return string
 *
 * @todo проверка на то, что header уже выполнен или вывод css в header
 */

function att_filebox($area, $item, $name = '', $type = 'all', $limit = -1)

Параметр name - по аналогии с функциями для вывода элементов форм. Имя поля. Необязательный параметр. Позволяет добавить элементу несколько наборов вложений.

Данный виджет использует css стили bootstrap 3. Если он не используется на сайте, добавте свои стили.


Примеры:

{USER_ID|att_filebox('user', $this, 'avatar', 'image', 1)}

{USER_ID|att_filebox('user', $this)}



В связи с добавлением нового параметра "поле" для вложений, изменились сигнатуры функций в т.ч. виджетов:



function att_count_files($area, $item, $field = '_all_')

function att_count_files($area, $item, $field = '_all_')

function att_get($area, $item, $field = '', $column = '', $number = 'first')

function att_count($area, $item, $field = '', $type = 'all')



function att_widget($area, $item, $field = '', $tpl = 'attach2.widget', $width = '100%', $height = '200')

function att_display($area, $item, $field = '',  $tpl = 'attach2.display', $type = 'all')

function att_downloads($area, $item, $field = '', $tpl = 'attach2.downloads')

function att_gallery($area, $item, $field = '', $tpl = 'attach2.gallery')



Не забудьте внести изменения в Ваши шаблоны



