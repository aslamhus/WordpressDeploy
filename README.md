# WordpressDeploy

## Push

To perform a complete push:

```bash
# env == staging | production
vendor/bin/yas-wpd push <env>

```

### Push options

```bash
# Default push
# You will be asked interactively if you'd like to push wp-content, the database, composer (if set), and whether you'd like to flush the cache
vendor/bin/yas-wpd push <env>
# Push without interaction. Assumes you want to push everything (wp-content,database,composer and flush cache)
vendor/bin/yas-wpd push <env> --no-interaction

## ‼️ Beware, setting any of the following options will disable interaction
## Push only wp-content
vendor/bin/yas-wpd push <env> --wp-content
## Push only db
vendor/bin/yas-wpd push <env> --db
## Push only composer
vendor/bin/yas-wpd push <env> --composer
## Flush the cache
vendor/bin/yas-wpd push <env> --flush-cache
## Push a combination
vendor/bin/yas-wpd push <env> --wp-content --composer --flush-cache

## Negate
## Alternatively , you can choose to negate certain options
## push everything except wp-content
vendor/bin/yas-wpd push <env> --no-wp-content
## push everything except db
vendor/bin/yas-wpd push <env> --no-db
## push everything except composer and do not flush cache
vendor/bin/yas-wpd push <env> --no-composer --no-flush-cache

```

## Deploy ignore files

Add a .deployginore file to your project root to exclude files in your public directory from deployment

For example:

```bash
*.tar.gz
wp-content
/test.txt
second-test.txt
```

The above will ignore:

- all files with ".tar.gz" extension in all directories,
- all directories of wp-content
- test.txt in the root directory only
- second-test.txt in all directories

### Unexpected behaviour

When using the `upload_type` "archive" instead of rsync in your .yaswpd.json, your deployignore patterns may produce slightly unexpected results. For example:

```bash
/*.tar.gz
test.txt
```

You might expect `/*.tar.gz` to only target files with extension .tar.gz in the root directory, but it will still target all files with that extension. Wildcards can't be anchored to the root directory. This is a quirk of both `gnutar` and `bsdtar`, which are used to archive your site before deploying. In this case, it's recommended to use explicit paths instead of wildcards.

## Choosing between rsync and archive for deployment

You can choose between rsyncing each file in public to your remote site, or archiving the public directory, pushing it to remote and then unzipping it on the server. The latter method is good for large scale changes, like uploading a new site for the first time. The former is more efficient when you only want to upload a few file changes and not the entire site.

Both methods will ignore files you set in your `.deployginore` (see above).

To change between `rsync` / `archive`, update your `upload_type` property in your `.yaswpd.json` file.

```json
{
  "upload_type": "rsync"
}
```

## Test against your .yaswpd.json file

You can run tests against your own settings. From your project root, run:

```bash
vendor/bin/yas-wpd test <testsuite>
```

List the available tests

```bash
vendor/bin/yas-wpd test --list
```

Perform a test of your settings json.

**_Note: this only tests the structure/type of your settings, not whether the filepaths exists._**

```bash
vendor/bin/yas-wpd test settings
```

You can get verbose testing output by passing the `-v` option.

## Assumptions

1. Assumes wordpress directory is in root / public directory. See .yaswpd.json settings.
2. Built to work with docker, but can in theory work without a docker container, i.e. in a MAMP / XAMPP environment

## Inject ENV Files

In your `.yaswpd.json` file, you can specify files that should be updated based on the environment (staging,local,production). See `files` property.

### Example

```js

{
    ...,
  "files": [
     [
      ".htaccess", // target file
      {
        "directory": "", // the directory to search for the target file and env file (defaults to 'public')
        "local": ".dev.htaccess", // files to replace based on environment
        "production": ".prod.htaccess",
        "staging": ".staging.htaccess"
      }
    ],
    [
      "wp-config.php",
      {
        "directory": "",
        "local": "wp-config-local.php",
        "production": "wp-config-production.php",
        "staging": "wp-config-staging.php"
      }
    ],
    [
      ".env",
      {
        "directory": "",
        "local": ".local.env",
        "production": ".prod.env",
        "staging": ".staging.env"
      }
    ]
  ],
}
```

Note: Make sure all the env files are in the same directory as the target file or you will encounter the error: `Target file not found: ' . $target_file. '. Please make sure that the injecting files exist in the same directory`

## Add your own custom scripts to the push process

The push command has two hooks for custom scripts, `prePush` and `postPush`. Here's an example of how to run a `prePush` script.

### Example

1. Add scripts to your .yaswpd.json file:

```json
  ...,
 "hooks": {
    "prePush": ["bin/test", "bin/test2"],
    "postPush": ["bin/cloudflare"]
  }
```

2. Make sure to give your scripts execute privileges by running `chmod +x <script>`

3. Add a hashbang to specify your script language

```php
##!/usr/bin/env php
<?php

echo "hey girl, this script triggers an error!";
exit(1);

```

### Exit code

If your custom script exits with a code any other than 0,the push script will throw an error and stop.

### Access .yaswpd.json settings in your script

```php
##!/usr/bin/env php
<?php

$settings = $_SERVER['settings'];
print_r(json_decode($settings));
exit;

```

## Troubleshooting

### Push errors

#### Cannot change mode to rwxrwxr-x: Operation not permitted

If you encounter this error, your SSH user does not have sufficient permissions on the host.

Change your SSH host and user settings to a user with the correct permissions. You may need to reset permissions on your server, or you may need to use a different user in your `.yaswpd.json` file who has sufficient permissions (such as root / admin).

This error is encountered when extracting the tarball of your wp-content files on the host. If you can't change your user to root, your files are most likely still being extracted. You may need to check this manually. If they are, you can ignore the message.

It's best to use the user who owns the file changes / permissions your site / application. Changing ownership may create other issues, such as `Rsync Permission denied (13)` when trying to push files.
