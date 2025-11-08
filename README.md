<!--
  - SPDX-FileCopyrightText: 2017 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Preview Generator

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/previewgenerator)](https://api.reuse.software/info/github.com/nextcloud/previewgenerator)

Nextcloud app that allows admins to pre-generate previews. The app listens to 
edit events and stores this information. Once a cron job is triggered it will
start preview generation. This means that you can better utilize your
system by pre-generating previews when your system is normally idle and thus 
putting less load on your machine when the requests are actually served.

This app is primarily meant for small Nextcloud servers running on cheap
hardware where on-demand generation of previews is not quick enough. The app
effectively trades higher disk usage for quicker previews and this trade-off
should be considered carefully. The previous reasoning of resource exhaustion
caused by too many concurrent preview requests is not a concern anymore as
there is a [configurable limit](https://docs.nextcloud.com/server/latest/admin_manual/configuration_server/config_sample_php_parameters.html#preview-concurrency-all)
in modern versions of Nextcloud.

The app does not replace on demand preview generation so if a preview is 
requested before it is pre-generated it will still be shown.

## How to install

* Install directly from within your Nextcloud from the [app store](https://apps.nextcloud.com/apps/previewgenerator)
* Clone this repository into your Nextcloud app folder

## How to use the app

1. Install the app
2. Enable the app
3. Run `./occ preview:generate-all` once after installation.
4. Add a (system) cron job for ` ./occ preview:pre-generate`
  * I run it every 10 minutes

## Known issues

* The app does not work with encryption enabled

## How does the app work

1. Listen to events that a file has been written or modified and store it in the database
2. On cron run request previews for the files that have been written or modified

If a preview already exists at step 2 then requesting it is really cheap. If not
it will be generated. Depending on the sizes of the files and the hardware you
are running on the time this takes can vary.

## Commands

#### `preview:generate-all [--workers=WORKERS] [--path=PATH ...] [user_id ...]`

Loop over all files and try to generate previews for them. If one or multiple user ids are supplied
it will just loop over the files of those users. You can also limit the generation to one or more
paths using `--path="/[username]/files/[folder path]"`, for example,
`--path="/alice/files/Photos"`. Note that all given user_ids are ignored if at least one path is
specified.

You can optionally spawn multiple parallel workers to speed up the generation of previews. Simply 
pass the desired worker count to the `--workers` option, for example, `--workers=10`.

Use `<command> -vv` to get a more verbose output if you are interested to see which files are being
processed.

#### `preview:pre-generate`

Do the actual pre-generation. This means only for new or modified files (since the app was enabled
or the last pre-generation was done). It is possible to run the command multiple times in parallel
to speed up the generation of previews.

Use `<command> -vv` to get a more verbose output if you are interested to see which files are being
processed.

## Available configuration options

The value of each option can either be a list of sizes separated by **spaces** or an empty string.
Setting an empty string will simply skip those kinds of previews.
Deleting or not setting a config will use a built-in default list of values for those previews.

* Preview sizes must be a power of 4! Other sizes are silently ignored.
* The smallest possible size is 64.
* The max size is determined by your `preview_max_x` and `preview_max_y` settings in `config.php`.

#### `occ config:app:set --value="64 256" previewgenerator squareSizes`
Cropped square previews which are mostly used in the list and tile views of the files app.

#### `occ config:app:set --value="256 4096" previewgenerator fillWidthHeightSizes`
Will retain the aspect ratio and try to use the given size for the longer edge.

#### `occ config:app:set --value="256 4096" previewgenerator coverWidthHeightSizes`
Will retain the aspect ratio and try to use the given size for the shorter edge.

#### `occ config:app:set --value="64 256 1024" previewgenerator widthSizes`
Will retain the aspect ratio and use the specified width. The height will be scaled according to
the aspect ratio.

#### `occ config:app:set --value="64 256 1024" previewgenerator heightSizes`
Will retain the aspect ratio and use the specified height. The width will be scaled according to
the aspect ratio.


## FAQ

### Why can't I use webcron?

Preview generation can be a very long running job. Thus we need a system that
does not time out.

### What if I'm using Nextcloud All In One?

Follow [these instructions](https://github.com/nextcloud/all-in-one/discussions/542)

### I don't want to generate all the preview sizes

The following options are recommended if you only want to generate a minimum set of required
previews.
This should include all previews requested by the files, photos and activity apps.

```
./occ config:app:set --value="64 256" previewgenerator squareSizes
./occ config:app:set --value="256 4096" previewgenerator fillWidthHeightSizes
./occ config:app:set --value="" previewgenerator widthSizes
./occ config:app:set --value="" previewgenerator heightSizes
```

This will only generate:
* Cropped square previews of: 64x64 and 256x256
* Aspect ratio previews with a max width **or** max height of: 256 and 4096

### I'm using the Memories app

You can generate an additional preview for the timeline view of the Memories app.

```
./occ config:app:set --value="256" previewgenerator coverWidthHeightSizes
```

### I get  "PHP Fatal error:  Allowed memory size of X bytes exhausted"
You need to increase the memory allowance of PHP, by default it is 128 MB. You do that by changing the memory_limit in the php.ini file.

If you use [the docker container](https://github.com/nextcloud/docker) you need set the environment variable `PHP_MEMORY_LIMIT` instead.

### I want to skip a folder and everything in/under it

Add an empty file with the name `.nomedia` in the folder you wish to skip. All files and subfolders of the folder containing `.nomedia` will also be skipped.

### I want to reset/regenerate all previews

**WARNING:** This is not supported but it has been confirmed to work by multiple users. Proceed at your own risk. Always keep backups around.

1. Remove the folder `your-nextcloud-data-directory/appdata_*/preview`
2. *Optional:* change parameters `preview_max_x` and `preview_max_y` in `config.php` (e.g., to `512`), and change the `previewgenerator` app parameters `heightSizes`, `squareSizes` and `widthSizes` as per the README (or better yet, to a low value each, e.g. `512`, `256` and `512` respectively)
3. Run `occ files:scan-app-data` (this will reset generated previews in the database)
4. Run `occ preview:generate-all [user-id]` (this will run very fast if you did step 2) 
