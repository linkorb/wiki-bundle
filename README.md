> [!WARNING]
> This is a read-only repository used to release the subtree. Any issues and pull requests should be forwarded to the
> upstream [Nebula repository](https://github.com/linkorb/nebula).

# Symfony 5|6|7 WikiBundle

`linkorb/wiki-bundle` adds a wiki to Symfony web applications.

## Features

- Store and manage wiki content in-app or from external sources
- Import/export wikis
- Easily track events and changes
- Create wiki pages from existing templates
- Format wiki pages with Markdown
- Enforce strict wiki access control

## Installation

Open a command console at the root of your project and execute the
following command.

```console
composer require linkorb/wiki-bundle
```

## Setup

1. Register the bundle's routes by creating a ***config/routes/linkorb-wiki-bundle.yaml*** file with the following as its contents.

    ```yaml
    wiki_bundle:
      resource: '@LinkORBWikiBundle/src/Controller'
      type: attribute
    ```

2. Append the following block in the application's base twig template.

    ```twig
      {% block sidebar %}{% endblock %}
      {% block submenu %}{% endblock %}
    ```

4. Configure the data directory in your services.yaml file

```yaml
parameters:
  wiki_bundle_data_dir: '%kernel.project_dir%/var'
```

## Wiki access control

> [!DEPRECATED]
Use [Symfony security roles](https://symfony.com/doc/current/security.html#roles) defined in your application to control who can access or modify a wiki by setting the appropriate rules in the **Read role** and **Write role** fields of the **Add wiki** form of a Symfony application.

To extend the capabilities of wiki (and their sub-pages) access control rules, the wikis have an editable
access control expression field. This field allows end-users to write additional rules using symfony's expression language.
Example: `is_granted("ROLE_ADMIN")`

The access control checks are left to the application embedding this bundle. One way you could do that would be in your
Voter class, which handles the `Wiki` and `WikiPage` entities (`$wikiPage->getWiki()`).

```php
  $aclExpression = $wiki->getAccessControlExpression();
  $hasAclExpression = !empty($aclExpression);
  return !$hasAclExpression || $aclEval->eval($aclExpression);
```

Assume in this example that `$aclEval` is an instance of [IsGrantedEval](src/AccessControl/IsGrantedEval.php) (can be autowired).

`IsGrantedEval` is the default provided implementation of the [AccessControl/EvalInterface](src/AccessControl/EvalInterface.php).
It provides a single function to use in the ACL expression; the `is_granted` function, which behaves like the built-in symfony
function.

If you want to extend the types of access control functions users have access to in the ACL expression field, you must
extend the `EvalInterface`, and swap out the implementation in the service config.

```yaml
  LinkORB\Bundle\WikiBundle\AccessControl\EvalInterface:
    alias: App\Security\MyAccessControlEvaluator
```

## Wiki configuration

The `config` field of this bundle's **Add wiki** form may be used to customize a wiki using the following settings (in YAML format):


<table>
  <tr>
    <th>Setting</th>
    <th>Type</th>
    <th>Description</th>
  </tr>

  <tr>
    <td><code>read-only</code></td>
    <td>Boolean</td>
    <td>Allows or denies users wiki page creation and editing permissions. This should be set to <code>true</code> if a wiki is externally managed (on GitHub for example).</td>
  </tr>

  <tr>
    <td><code>pull</code>
    <td>Array</td>
    <td>
      Settings for pulling changes from a wiki that is managed on a third-party platform like GitHub. Each item (repository) in the array takes the following <strong>required</strong> settings:
      <ul>
        <li><code>type</code>: The project/version management type. Valid options include <strong>rest</strong>,<strong>clickup</strong>, and <strong>git</strong>. However, only <code>git</code> is supported at this time.</li>
        <li><code>url</code>: The target repository's HTTPS URL.</li>
        <li><code>secret</code>: A <a href="https://github.com/settings/personal-access-tokens">fine-grained</a> or a <a href="https://github.com/settings/personal-access-tokens">classic</a> GitHub personal access token that has <code>push</code> and <code>pull</code> permissions to the target repository specified in the <code>url</code> field.
        </li>
      </ul>
    </td>
  </tr>

  <tr>
    <td><code>push</code>
    <td>Array</td>
    <td>
      Settings for pushing changes from a wiki to a third-party platform where the wiki's content is stored. Each item (repository) in the array takes the following <strong>required</strong> settings:
      <ul>
        <li><code>type</code>: The project/version management type. Valid options include <strong>rest</strong>,<strong>clickup</strong>, and <strong>git</strong>. However, only <code>git</code> is supported at this time.</li>
        <li><code>url</code>: The target repository's HTTPS URL.</li>
        <li><code>secret</code>:A <a href="https://github.com/settings/personal-access-tokens">fine-grained</a> or a <a href="https://github.com/settings/personal-access-tokens">classic</a> GitHub personal access token that has <code>push</code> and <code>pull</code> permissions to the target repository specified in the <code>url</code> field.
        </li>
      </ul>
    </td>
  </tr>
</table>


#### Sample wiki configuration

For example, setting the following wiki configuration prevents users from creating and editing wiki pages that are managed externally (e.g., in a GitHub repository).

```yaml
# wiki.config

read-only: true

push:
  - type: git
    url: https://github.com/<USERNAME>/<WIKI-REPOSITORY.git>
    secret: `ENV:WIKI_GIT_TOKEN`

pull:
  - type: git
    url: https://github.com/<USERNAME>/<WIKI-REPOSITORY.git>
    secret: `ENV:WIKI_GIT_TOKEN`
```

> [!TIP]
> Git publish and pull links are in the wiki page's admin dropdown.

> [!NOTE]
> If the GitHub repository is not empty, pull from it first to sync the repository before pushing any changes.

## Brought to you by the LinkORB Engineering team

<img src="http://www.linkorb.com/d/meta/tier1/images/linkorbengineering-logo.png" width="200px" /><br />
Check out our other projects at [engineering.linkorb.com](https://engineering.linkorb.com).
