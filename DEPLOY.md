# Steps to deploy
## Preparation
1. Make sure the code is properly formatted.
   ```bash
   vendor/bin/pretty
   ```
   > If it shows formatting errors, then you can fix them with the `vendor/bin/pretty fix` command
2. Run tests
   ```bash
   vendor/bin/phpunit tests
   ```
## Publish
- Via git tag
    1. Create a new version tag.
       ```bash
       git tag v1.2.6
       ```

    2. Push the tag.
       ```bash
       git push origin --tags
       ```
- Via Github release 

  Create a new [Github release](https://github.com/configcat/go-sdk/releases) with a new version tag and release notes.