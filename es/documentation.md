---
title: Documentación
description: Activities management
---

Este complemento fue desarrollado por la [Asociación
l'Aphyllanthe](https://www.aphyllanthe.fr/). Ofrece:

* gestión de actividades,
* gestión de suscripciones.

## Instalación

Antes que todo, descargue el complemento:

* [Get latest Activities
  plugin!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Get Activities plugin nightly
  build!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Extraer el archivo descargado en el directorio de Galette `plugins`. Por
ejemplo, bajo linux (reemplazar `{url}` y `{version}` con valores correctos):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Inicio de la base de datos

Para funcionar, este plugin requiere varias tablas en la base de datos. Ver
[Interfaz de gestión de plugins
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

Y se acabó; el complemento de actividades está instalado :)
