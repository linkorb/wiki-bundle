> [!WARNING]
> This is a read-only repository used to release the subtree. Any issues and pull requests should be forwarded to the
> upstream Nebula repository.c

## Symfony 5|6|7 WikiBundle

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
    LinkORB\Bundle\WikiBundle\LinkORBWikiBundle::class => ['all' => true],
    // ...
];
```

Create routing file to enable routes from wiki bundle like this:
`config/routes/linkorb-wiki-bundle.yaml`
And add loading of routes at next way:
```yaml
wiki_bundle:
  resource: '@LinkORBWikiBundle/src/Controller'
  type: attribute
```

### Step 3: Append twig block in base twig file
```twig
  {% block sidebar %}{% endblock %}
  {% block submenu %}{% endblock %}
```

### Step 4: Enjoy :)

---
### Store/sync wiki's page content on git/GitHub.

The repository this feature supports storing and retrieving wiki page content on git/Github.  For that, configure git/Github details into the wiki config field.  Currently, support git/GitHub web URL(HTTPS) for pull and push.


Example config in the wiki.config

```
push:
  - type: git
    url: https://github.com/gitHub-username/wiki-git.git
    secret: `ENV:WIKI_GIT_TOKEN` # defines which env to use as a secret

pull:
  - type: git
    url: https://github.com/gitHub-username/wiki-git.git
    secret: `ENV:WIKI_GIT_TOKEN` # defines which env to use as a secret
```

- `type`: push target type option(rest, clickup, git) Currently support only `git` option.
- `url`: GitHub Clone URL(HTTPS) where pull/push content.
- `secret:` Personal access tokens for authentication. [How to create a personal access token?](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-personal-access-token-classic)


Git publish and pull links are in the wiki page admin dropdown.

`Note:` If the GitHub repository is not empty, Pull first to sync the repository.

---

### Read-only wikis

This feature supports preventing users from editing wiki page content that is being managed in git/Github.
For that, set config option into wiki config field.
```
read-only: true
```
`read-only`: boolen true/false value. If this is set to true, the edit and add features are prohibited for the user.
