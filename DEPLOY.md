# Steps to deploy
## Preparation
1. Install dependencies
   ```bash
   composer install
   ```
2. Make sure the code is properly formatted.
   ```bash
   vendor/bin/php-cs-fixer fix --ansi --diff --dry-run
   ```
   > If it shows formatting errors, then you can fix them with the `vendor/bin/php-cs-fixer fix --ansi` command
3. Make sure the static analyzer doesn't show errors.
   ```bash
   vendor/bin/phpstan analyse --ansi
   ```
4. Run tests
   ```bash
   vendor/bin/phpunit tests
   ```
5. Set `SDK_VERSION` constant in `ConfigCatClient.php`
6. Commit & Push
## Publish
- Via git tag
    1. Create a new version tag.
       ```bash
       git tag v[MAJOR].[MINOR].[PATCH]
       ```
       > Example: `git tag v1.3.5`
    2. Push the tag.
       ```bash
       git push origin --tags
       ```
- Via Github release 

  Create a new [Github release](https://github.com/configcat/php-sdk/releases) with a new version tag and release notes.

## Packagist
Make sure the new version is available on [Packagist](https://packagist.org/packages/configcat/configcat-client).

## Update samples
Update and test sample apps with the new SDK version.
```bash
composer update configcat/configcat-client
```

To validate installed package version
```bash
composer show
```