---
title: Documentação
description: Activities management
---

Este plugin foi desenvolvido para a [Associação
l'Aphyllanthe](https://www.aphyllanthe.fr/). Ele oferece :

* gestão de atividades,
* Gestão de assinaturas.

## Instalação

Primeiramente, baixe o plugin:

* [Get latest Activities
  plugin!](https://github.com/galette-plugins/plugin-activities/releases/latest)
* [Get Activities plugin nightly
  build!](https://github.com/galette-plugins/plugin-activities/releases/tag/nightly)

Extraia o arquivo baixado no diretório `plugins` do Galette. Por exemplo, no
Linux (substituindo `{url}` e `{version}` pelos valores corretos):

```bash
$ cd /var/www/html/galette/plugins
$ wget {url}
$ tar xjvf galette-plugin-activities-{version}.tar.bz2
```

## Inicialização do banco de dados

Para funcionar, este plugin requer várias tabelas no banco de dados. Consulte
[Interface de gerenciamento de plugins do
Galette](https://doc.galette.eu/en/master/plugins/index.html#plugins-managment).

E está concluído; o plugin Activities está instalado :)
