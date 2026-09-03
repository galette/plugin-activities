---
title: Documentazione
description: Activities management
---

Questo plugin è stato sviluppato per [Association
l'Aphyllanthe](https://www.aphyllanthe.fr/). Fornisce:

* gestione attività,
* gestione iscrizioni.

## Installazione

Prima di tutto, scaricare il plugin:

* [Get latest Activities
  plugin!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Get Activities plugin nightly
  build!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Estrarre l'archivio scaricato nella directory `plugins` di Galette. Ad esempio,
su Linux (sostituendo `{url}` e `{version}` con i valori corretti):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Inizializzazione database

Per funzionare, questo plugin richiede diverse tabelle nel database. Vedere
[Interfaccia di gestione dei plugin
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

E questo è finito; il plugin Attività è installato :)
