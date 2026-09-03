---
title: Документація
description: Activities management
---

Цей плагін розроблено для [Association
l'Aphyllanthe](https://www.aphyllanthe.fr/). Він забезпечує:

* управління діяльністю,
* управління підписками.

## Встановлення

Перш за все, завантажте плагін:

* [Get latest Activities
  plugin!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Get Activities plugin nightly
  build!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Розпакуйте завантажений архів у каталог Galette `plugins`. Наприклад, під Linux
(замінивши `{url}` і `{version}` на правильні значення):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Ініціалізація бази даних

Для роботи цього плагіна потрібно кілька таблиць у базі даних. Перегляньте
[Інтерфейс керування плагінами
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

І це закінчено; Плагін Activities встановлено :)
