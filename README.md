## Symfony 4,5 WikiBundle

Installation
============

### Step 1: Download the Bundle

Open a command console, enter your project directory and execute the
following command to download the latest stable version of this bundle:

```console
$ composer require linkorb/wiki-bundle
```

This command requires you to have Composer installed globally, as explained
in the [installation chapter](https://getcomposer.org/doc/00-intro.md)
of the Composer documentation.

### Step 2: Enable the Bundle

Then, enable the bundle by adding it to the list of registered bundles
in the `config/bundles.php` file of your project (if it isn't exists yet):

```php
<?php
// config/bundles.php

return [
    // ...
    LinkORB\Bundle\WikiBundle\WikiBundle::class => ['all' => true],
    // ...
];
```

Create routing file to enable routes from wiki bundle like this:
`config/routes/linkorb-wiki-bundle.yaml`
And add loading of routes at next way:
```yaml
wiki_bundle:
  resource: '@WikiBundle/Controller'
  type: annotation
```

### Step 3: Enjoy :)
