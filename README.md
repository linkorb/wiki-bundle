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

1. Ensure the bundle is registered in your Symfony application by adding the following to the ***config/bundles.php*** script if the entry doesn't already exist.

    ```php
    <?php
    // config/bundles.php

    return [
        // ...
        LinkORB\Bundle\WikiBundle\LinkORBWikiBundle::class => ['all' => true],
        // ...
    ];
    ```

2. Register the bundle's routes by creating a ***config/routes/linkorb-wiki-bundle.yaml*** file with the following as its contents.

    ```yaml
    wiki_bundle:
      resource: '@LinkORBWikiBundle/src/Controller'
      type: attribute
    ```

3. Append the following block in the application's base twig template.

    ```twig
      {% block sidebar %}{% endblock %}
      {% block submenu %}{% endblock %}
    ```

## Wiki access control

Use [Symfony security roles](https://symfony.com/doc/current/security.html#roles) defined in your application to control who can access or modify a wiki by setting the appropriate rules in the **Read role** and **Write role** fields of the **Add wiki** form in the application.

## Wiki configuration

The `config` field of the bundle's **Add wiki** form may be used to customize a wiki using the following settings (in YAML format):


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
        <li><code>type</code>: The project/version management type. Valid options include <strong>rest</strong>,<strong>clickup</strong>, and <strong>git</strong>. However, only the <code>git</code> is supported at this time.</li>
        <li><code>url</code>: </li>
        <li><code>secret</code>: </li>
    </td>
  </tr>

  <tr>
    <td><code>pull</code>
    <td>Array</td>
    <td>
      Settings for pushing changes from a wiki to a third-party platform where the wiki's content is stored. Each item (repository) in the array takes the following <strong>required</strong> settings:
      <ul>
        <li><code>type</code>: The project/version management type. Valid options include <strong>rest</strong>,<strong>clickup</strong>, and <strong>git</strong>. However, only the <code>git</code> is supported at this time.</li>
        <li><code>url</code>: </li>
        <li><code>secret</code>: </li>
    </td>
  </tr>
  <!-- - `type`: push target type option(rest, clickup, git) Currently support only `git` option.
  - `url`: HTTPS (HTTPS) where pull/push content.
  - `secret:` Personal access tokens for authentication. [How to create a personal access token?](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/managing-your-personal-access-tokens#creating-a-personal-access-token-classic)
- `push`: An array of settings for pulling changes from a wiki that is managed on GitHub. Each repository's settings is the same as those in the `pull` setting above. -->
</table>

> [!TIP]
> Git publish and pull links are in the wiki page admin dropdown.

> [!NOTE]
> If the GitHub repository is not empty, Pull first to sync the repository.

## Store and sync wiki content on/from other platforms

In addition to managing a wiki entry from the application's user interface, this bundle can render wiki content managed on external platforms.

For example, you may store your wiki's content in a GitHub repository and push/pull changes to/from the wiki's pages over HTTPS by adding the following to the `config` field of the **Add wiki** form.

```yaml
push:
  - type: git
    url: https://github.com/gitHub-username/wiki-git.git
    secret: `ENV:YOUR_GITHUB_ACCESS_TOKEN`

pull:
  - type: git
    url: https://github.com/gitHub-username/wiki-git.git
    secret: `ENV:YOUR_GITHUB_ACCESS_TOKEN`
```

### Read-only wikis

This feature supports preventing users from editing wiki page content that is being managed in git/Github.
For that, set config option into wiki config field.
```
read-only: true
```
`read-only`: boolen true/false value. If this is set to true, the edit and add features are prohibited for the user.

## Brought to you by the LinkORB Engineering team

<img src="http://www.linkorb.com/d/meta/tier1/images/linkorbengineering-logo.png" width="200px" /><br />
Check out our other projects at [engineering.linkorb.com](https://engineering.linkorb.com).
