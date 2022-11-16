# conditional-regex-search-and-replace-action
This action executes conditional search and replace operations on a set of files. Strings in files are replaced when they match given regular expressions.

Originally, this action was created to update parameters (like Docker image tag and Git branch) in Helm charts and Kustomize templates in GitOps repositories.
When using the [environment-per-folder strategy](https://dreitier.com/knowledge-base/continuous-delivery-and-deployment/using-environment-per-folder-directory-structure-for-gitops-projects-with-argo-cd-and-helm), locating the environments to update can be cumbersome.
If you want to use this action indeed for updating GitOps repositories, please look at [this article]() on how to execute it with `repository.dispatch`.

## Usage

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
env:
with:
  mappings: "docker_image_tag==main.* {THEN_UPDATE_FILES} **dev/values.yaml=docker_image_tag_regex"
  docker_image_tag: "${{ github.sha }}"
  docker_image_tag_regex: "imageTag: \\\"(?<docker_image_tag>.*)\\\""
```

### Mandatory arguments

| Argument | Description |
| --- | --- |
| `mappings` | DSL to define search-and-replace operations, see below |

### Optional arguments
| Argument                          | Default | Description                                                                                                                                             |
|-----------------------------------| --- |---------------------------------------------------------------------------------------------------------------------------------------------------------|
| `directory`                       | `$CWD` | Directory, in which to operate. By default, the base directory is used.                                                                                 |
| `dump`                            | `<none>` | If `1`, it dumps the provided configuration                                                                                                             | 
| `if_no_match_fail`                | `0` | If the action has not modified any file and `if_no_match_fail` is `1`, it will fail with exit code `3`                                                  |
| `if_well_known_vars_missing_fail` | `0` | If `1`, it fails with exit code `2` if none of docker_image_tag, git_branch or git_tag is provided                                                      |
| `updated_file_suffix`             | `<none>` | If set, any changes will be written to another file the path of the original file and that suffix                                                       |
| `register_custom_regexes`         | `<none>` | A comma-separated list of custom regular expressions to register                                                                                        |
| `register_custom_variables`       | `<none>` | A comma-separated list of custom variables to register                                                                                                  |
| `commit`                          | `0` | If there has been any modified files (see below, `outputs.total_modified_files`), it will commit the changes to the Git repository                      |
| `commit_template`                 | `<none>` | Template to use for the commit message. You can use the [Blade template engine](https://laravel.com/docs/9.x/blade) to dynamically specify the message. |
| `committer_name`                  | `<none>` | Name of committer, only relevant if `commit` is present                                                                                                 |
| `committer_email`                 | `<none>` | E-mail of committer, only relevant if `commit` is present                                                                                                            |

### Well-known variables and regular expressions
Due the original requirement of this action, you can use the following GitOps-related arguments:

| Argument | Description |
| --- | --- |
| `docker_image_tag` | Docker image tag created by upstream repository |
| `docker_image_tag_regex` | Regular expression to modify occurences of Docker image tags in globbed files |
| `git_tag` | Git tag created by upstream repository |
| `git_tag_regex` | Regular expression to modify occurences of Git tags in globbed files |
| `git_branch` | Git branch modified in upstream repository |
| `git_branch_regex` | Regular expression to modify occurences of Git branch in globbed files |

There is __no__ need to use `docker_image_tag_regex`, `git_tag_regex` and `git_branch_regex`. You can register additional regular expressions as you like.

#### Registering additional regular expressions
Additional regular expressions must be provided in the following format:

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  register_custom_regexes: check_for_customer_regex
env:
  CHECK_FOR_CUSTOMER_REGEX: "/customer: (<customer_number>\d+)/"
```

To register mulitple regular expressions, use a comma (`,`) for separation:

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  register_custom_regexes: check_for_customer_regex, project_id_regex
env:
  CHECK_FOR_CUSTOMER_REGEX: "/customer: (?<customer_number>\d+)/"
  PROJECT_ID_REGEX: "/project: (?<project_id>\d+)/"
```


#### Registering additional variables
To provide additional variables aside from `docker_image_tag`, `git_tag` and `git_branch` you have to register those variables.
For referencing the `customer_number` variable of the previous section, you have to register it:

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  register_custom_variables: customer_number
env:
  CUSTOMER_NUMBER: "555"
```

To register multiple variables, use a comma (`,`) for separation:

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  register_custom_variables: customer_number, project_id
env:
  CUSTOMER_NUMBER: "555"
  PROJECT_ID: "5550555
```

#### Git commit message templates
When using `commit_template`, you can either put in some static string or you can use the [Blade template engine](https://laravel.com/docs/9.x/blade) to dynamically specify the message.
Each of the well-known and custom variables is passed as variable to the template. If you have a custom variable `customer_number`, you can access the value with `{{ $customer_number }}`.

Use a static string:
```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  commit_template: 'A new version has been born.'
```

Reference a well-known variable:
```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  docker_image_tag: '1.0.0'
  commit_template: 'chore: Update to Docker image tag {{ $docker_image_tag }}'
```

Reference a custom variable:
```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  register_custom_variables: customer_number
  docker_image_tag: '1.0.0'
  commit_template: 'chore: Update to Docker image tag {{ $docker_image_tag } for customer {{ $customer_number))'
env:
    CUSTOMER_NUMBER: "555" # or set it to an empty string
```

Expressions:
```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  # ...
  register_custom_variables: customer_number
  docker_image_tag: '1.0.0'
  commit_template: 'chore: Update to Docker image tag {{ $docker_image_tag }}@if(!empty($customer_number)) for customer {{ $customer_number }}@endif'
env:
    CUSTOMER_NUMBER: "" # or a non-empty string
```

#### Mappings
Mappings specify, if one or multiple regular expressions match, how other regular expressions are applied. It's basically

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
with:
  mappings: '<if_first_regex_match> OR <if_second_regex_match> {THEN_UPDATE_FILES} <change_first_file_group>=<apply_regex_1> {AND} <change_second_file_group>=<apply_regex_1>&<apply_regex_2>'
```

In each regular expression to apply, you have access to all well-known and custom variables through named capturing groups:

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
  register_custom_variables: customer_number
  register_custom_regexes: my_regex
  docker_image_tag: '1.0.0'
  docker_image_tag_regex: 'imageTag: \"(?<docker_image_tag).*)\"'
  mappings: 'docker_image_tag==.* {THEN_UPDATE_FILES} **.yaml=docker_image_tag_regex&my_regex'
env:
  CUSTOMER_NUMBER: "555"
  MY_REGEX: '.*customer: \"(?<customer_number>.*)\".*'
```

### Outputs

| Output | Default | Description                                        |
| --- | --- |----------------------------------------------------|
| `total_modified_files` | `0` | Number of modified (target) files.                 |
| `$your_variable` | `` | Any of your provded well-known or custom variables |

You can reference the output variables in your GitHub Action workflow like this:

```yaml
jobs:
  my_job_with_dedicated_commit_and_push:
    runs-on: ubuntu-latest
    steps:
      - name: Update repository  
        id: search_and_replace_op
        uses: dreitier/conditional-regex-search-and-replace-action
        with:
          # ...

      # instead of using this action's commit hook, we use the more flexible EndBug/add-and-commit if any file has been modified
      - name: Commit and push
        if: ${{ steps.search_and_replace.outputs.total_modified_files >= 1 }}
        uses: EndBug/add-and-commit@v7
        with:
          author_name: build@internalcom
          author_email: build@internal
          message: "There have been {{ steps.search_and_replace.outputs.total_modified_files }} modified files"
```

To access e.g. the `customer_number` from before

```yaml
jobs:
  my_job_with_dedicated_commit_and_push:
    runs-on: ubuntu-latest
    steps:
      - name: Update repository
        id: search_and_replace_op
        uses: dreitier/conditional-regex-search-and-replace-action
        with:
          register_custom_variables: customer_number
          docker_image_tag: '1.0.0'
          # ...
        env:
            CUSTOMER_NUMBER: "555"

      - name: Commit and push
        if: ${{ steps.search_and_replace.outputs.total_modified_files >= 1 }}
        uses: EndBug/add-and-commit@v7
        with:
          author_name: build@internal
          author_email: build@internal
          message: "Customer number {{ steps.search_and_replace.outputs.customer_number }} has {{ steps.search_and_replace.outputs.total_modified_files }} modified files for tag {{ steps.search_and_replace.outputs.docker_image_tag }}"
```

## Examples
### Updating strings in multiple files

In a folder structure like this
```
.
+-- asia
��� +-- dev
��� ��� +-- values.yaml
��� +-- prod
���     +-- values.yaml
+-- eu
    +-- dev
    ��� +-- values.yaml
    ��� +-- values.yaml.new
    +-- prod
```

the files `eu/dev/values.yaml` and `asia/dev/values.yaml` have be modified. The original content of both `values.yaml` files looks like this:

```yaml
custom_parameter: custom_value
imageTag: v0.1.0
other_parameter: other_custom_value
```

We want to update the `imageTag` value in both `values.yaml` files from `v0.1.0` to `v0.2.0`:

```yaml
uses: dreitier/conditional-regex-search-and-replace-action
env:
with:
  mappings: "docker_image_tag==v.* {THEN_UPDATE_FILES} **dev/values.yaml=docker_image_tag_regex"
  docker_image_tag: "v0.2.0"
  docker_image_tag_regex: "imageTag: \\\"(?<docker_image_tag>.*)\\\""
```

After running `conditional-regex-search-and-replace-action`, both files will look like this:
```yaml
custom_parameter: custom_value
imageTag: v0.2.0
other_parameter: other_custom_value
```

#### Transformed into pseudo code
Sometimes it's easier to read pseudo code instead of skimming through YAML files. The above YAML's definition can be read as
```
if $docker_image_tag =~ /v.*/ then
    $files = glob_files_with_matcher("**dev/values.yaml")
    
    foreach $files as $file
        foreach $line in $file
            if $line =~ /imageTag: \"(?<docker_image_tag>.*)\"/ then
                $line = "imageTag: \"$docker_image_tag\""
                write_line_to_file($file, $line)
            fi
        endforeach
    endforeach
endif
```

### Updating multiple values at the same time
When you need to apply multiple regexes at the same time, you can chain those regexes with:

```yaml
with:
  mappings: "docker_image_tag==v.* {THEN_UPDATE_FILES} **dev/values.yaml=docker_image_tag_regex&git_branch_regex"
  git_branch: "feature/165-integrate-kerberos-auth"
  git_branch_regex: "branch: \\\"(?<git_branch>.*)\\\""
  docker_image_tag: "v0.2.0"
  docker_image_tag_regex: "imageTag: \\\"(?<docker_image_tag>.*)\\\""
```

A `values.yaml` of this:
```yaml
custom_parameter: custom_value
imageTag: v0.2.0
branch: old-branch
```

will be transformed into this:
```yaml
custom_parameter: custom_value
imageTag: v0.2.0
branch: feature/165-integrate-kerberos-auth
```

#### Transformed into pseudo code
```
if $docker_image_tag =~ /v.*/ then
    $files = glob_files_with_matcher("**dev/values.yaml")
    
    foreach $files as $file
        foreach $line in $file
            if $line =~ /imageTag: \"(?<docker_image_tag>.*)\"/ then
                $line = "imageTag: \"$docker_image_tag\""
                write_line_to_file($file, $line)
            fi
            if $line =~ /branch: \"(?<git_branch>.*)\"/ then
                $line = "git_branch: \"$git_branch\""
                write_line_to_file($file, $line)
            fi
        endforeach
    endforeach
endif
```

### Check, if at least one variable matches
Sometimes you want to update files if at least one of multiple conditions is valid. `mappings` support a simple `{OR}` operator.

```yaml
with:
  mappings: "docker_image_tag==v.* {OR} git_branch==feature\/.* {THEN_UPDATE_FILES} **dev/values.yaml=docker_image_tag_regex&git_branch_regex"
  git_branch: "feature/165-integrate-kerberos-auth"
  git_branch_regex: "branch: \\\"(?<git_branch>.*)\\\""
  docker_image_tag: "latest"
  docker_image_tag_regex: "imageTag: \\\"(?<docker_image_tag>.*)\\\""
```

#### Transformed into pseudo code
```
if $docker_image_tag =~ /v.*/ or $git_branch =~ /feature\/.*/ then
    $files = glob_files_with_matcher("**dev/values.yaml")
    
    foreach $files as $file
        foreach $line in $file
            if $line =~ /imageTag: \"(?<docker_image_tag>.*)\"/ then
                $line = "imageTag: \"$docker_image_tag\""
                write_line_to_file($file, $line)
            fi
            if $line =~ /branch: \"(?<git_branch>.*)\"/ then
                $line = "git_branch: \"$git_branch\""
                write_line_to_file($file, $line)
            fi
        endforeach
    endforeach
endif
```

### Multiple mappings
This action's DSL for defining mappings does __not__ feature a `{AND}` condition in the left-hand part of `{THEN_UPDATE_FILES}`. Instead, you can chain multiple mappings together. Use De Morgan's law for complex conditions ;-)
Each of those mappings will be separately evaluated:

```yaml
with:
  mappings: "docker_image_tag==v.* {THEN_UPDATE_FILES} **dev/values.yaml=docker_image_tag_regex {NEXT_MAPPING} git_branch==feature\/.* {THEN_UPDATE_FILES} **dev/values.yaml=git_branch_regex"
  git_branch: "feature/165-integrate-kerberos-auth"
  git_branch_regex: "branch: \\\"(?<git_branch>.*)\\\""
  docker_image_tag: "v2.0.0"
  docker_image_tag_regex: "imageTag: \\\"(?<docker_image_tag>.*)\\\""
```

#### Transformed into pseudo code
```
if $docker_image_tag =~ /v.*/ then
    $files = glob_files_with_matcher("**dev/values.yaml")
    
    foreach $files as $file
        foreach $line in $file
            if $line =~ /imageTag: \"(?<docker_image_tag>.*)\"/ then
                $line = "imageTag: \"$docker_image_tag\""
                write_line_to_file($file, $line)
            fi
        endforeach
    endforeach
endif

if $git_branch =~ /feature\/.*/ then
    $files = glob_files_with_matcher("**dev/values.yaml")
    
    foreach $files as $file
        foreach $line in $file
            if $line =~ /branch: \"(?<git_branch>.*)\"/ then
                $line = "git_branch: \"$git_branch\""
                write_line_to_file($file, $line)
            fi
        endforeach
    endforeach
endif
```

## FAQ
### Why not using something more GitOps-esk like Argo CD Image Updater?
Using Argo CD Image Updater is totally fine but might have some drawbacks:
- By default, Argo CD Image Updater checks the Docker registries every 2 minutes. There might be a good chance of hitting API request limits, e.g. with Docker Hub.
- Setting up Argo CD Image Updater might be difficult, depending upon the environment.
- Argo CD Image Updater does not support complex search-and-replace operations.

When using `conditional-regex-search-and-replace-action`, you can either configure Webhooks in your GitOps repository to notify Argo CD or let Argo CD pull the latest version.

### Why are you not using Bash/$your_favorite_language_here for this action?
Before introducing this action, I've developed a Bash script for updating various GitOps repositories. Different projects had different requirements: The Bash script was no longer maintainable.
Using Laravel Zero and Pest for developing testable GitHub Actions looks fine to me.

### But I can do this with awk & sed.
Just keep using `awk` & `sed`, it's fine!

### Why looks the DSL like it does?
Due to the nature of complexity and how parameters are passed from the GitHub workflow to single actions, it was the first approach I came up with.
I also thought about using Blade templates for doing the mapping stuff.

## DSL spec

```
next_mapping            = "{NEXT_MAPPING}"
if_match_then_execute   = "{THEN_UPDATE_FILES}"
glob                    = $valid_glob
regex                   = $valid_quoted_regex
variable_reference      = (lower_case_chars | digits | '_')+
regex_reference         = (lower_case_chars | digits | '_')+
matcher                 = variable_reference "==" regex
or_matcher              = "{OR}"
matchers                = matcher (or_matcher matcher)+

regex_references        = regex_reference ('&' regex_reference)+
replacer                = glob '=' regex_references
replacers               = replacer (',' replacer)+
mapping                 = matchers if_match_then_execute replacers
multiple_mappings       = mapping (next_mapping mapping)+
```

## Support
This software is provided as-is. You can open an issue in GitHub's issue tracker at any time. But we can't promise to get it fixed in the near future. If you need professionally support, consulting or a dedicated feature, please get in contact with us through our website.

## Contribution
Feel free to provide a pull request.

## License
This project is licensed under the MIT License - see the LICENSE.md file for details.
