#!/usr/bin/env bash
SCRIPT="$(readlink --canonicalize-existing "$0")"
SCRIPTPATH="$(dirname "$SCRIPT")"

IS_GITHUB=""
if [ "$GITHUB_ACTION" != "" ]; then
	IS_GITHUB="--github"
fi

MAPPINGS=
if [ "$INPUT_MAPPINGS" != "" ]; then
	MAPPINGS=--mappings=${INPUT_MAPPINGS}
fi

DIRECTORY=
if [ "$INPUT_DIRECTORY" != "" ]; then
	DIRECTORY=--directory=$INPUT_DIRECTORY
fi

DUMP=
if [ "$INPUT_DUMP" = "1" ]; then
	DUMP=--dump
fi

IF_NO_MATCH_FAIL=
if [ "$INPUT_IF_NO_MATCH_FAIL" = "1" ]; then
	IF_NO_MATCH_FAIL=--require-at-least-one-change
fi

IF_WELL_KNOWN_VARS_MISSING_FAIL=
if [ "$INPUT_IF_WELL_KNOWN_VARS_MISSING_FAIL" = "1" ]; then
	IF_WELL_KNOWN_VARS_MISSING_FAIL=--require-one-well-known-var
fi

ENABLE_COMMIT=
if [ "$INPUT_ENABLE_COMMIT" = "1" ]; then
	ENABLE_COMMIT=--commit
fi 

COMMIT_TEMPLATE=
if [ "$INPUT_COMMIT_TEMPLATE" != "" ]; then
	COMMIT_TEMPLATE=--commit-template=$INPUT_COMMIT_TEMPLATE
fi

COMMITS_SPLIT_UP_BY=
if [ "$INPUT_COMMITS_SPLIT_UP_BY" != "" ]; then
	COMMITS_SPLIT_UP_BY=--commit-split-up-by=$COMMITS_SPLIT_UP_BY
fi

UPDATED_FILE_SUFFIX=
if [ "$INPUT_UPDATED_FILE_SUFFIX" != "" ]; then
	UPDATED_FILE_SUFFIX=--updated-file-suffix=$UPDATED_FILE_SUFFIX
fi

REGISTER_CUSTOM_REGEXES=
if [ "$INPUT_REGISTER_CUSTOM_REGEXES" != "" ]; then
	REGISTER_CUSTOM_REGEXES=--custom-regexes=$REGISTER_CUSTOM_REGEXES
fi

REGISTER_CUSTOM_VARIABLES=
if [ "$INPUT_REGISTER_CUSTOM_VARIABLES" != "" ]; then
	REGISTER_CUSTOM_VARIABLES=--custom-variables=$REGISTER_CUSTOM_VARIABLES
fi

# export default environment variables
if [ "$INPUT_DOCKER_IMAGE_TAG" != "" ]; then
	export DOCKER_IMAGE_TAG=$INPUT_DOCKER_IMAGE_TAG
fi

if [ "$INPUT_DOCKER_IMAGE_TAG_REGEX" != "" ]; then
	export DOCKER_IMAGE_TAG_REGEX=$INPUT_DOCKER_IMAGE_TAG_REGEX
fi

if [ "$INPUT_GIT_TAG" != "" ]; then
	export GIT_TAG=$INPUT_GIT_TAG
fi

if [ "$INPUT_GIT_TAG_REGEX" != "" ]; then
	export GIT_TAG_REGEX=$INPUT_GIT_TAG_REGEX
fi

if [ "$INPUT_GIT_BRANCH" != "" ]; then
	export GIT_BRANCH=$INPUT_GIT_BRANCH
fi

if [ "$INPUT_GIT_BRANCH_REGEX" != "" ]; then
	export GIT_BRANCH_REGEX=$INPUT_GIT_BRANCH_REGEX
fi

# the followings quotes the named parameter --mappings (and others) properly:
# ${MAPPINGS:+"$MAPPINGS"}
$SCRIPTPATH/application update-environments \
	$IS_GITHUB \
	${MAPPINGS:+"$MAPPINGS"} \
	${DIRECTORY:+"$DIRECTORY"} \
	${DUMP} \
	${IF_NO_MATCH_FAIL} \
	${IF_WELL_KNOWN_VARS_MISSING_FAIL} \
	${ENABLE_COMMIT} \
	${COMMIT_TEMPLATE:+"$COMMIT_TEMPLATE"} \
	${COMMITS_SPLIT_UP_BY:+"$COMMITS_SPLIT_UP_BY"} \
	${UPDATED_FILE_SUFFIX:+"$UPDATED_FILE_SUFFIX"} \
	${REGISTER_CUSTOM_REGEXES:+"$REGISTER_CUSTOM_REGEXES"} \
	${REGISTER_CUSTOM_VARIABLES:+"$REGISTER_CUSTOM_VARIABLES"}
	
