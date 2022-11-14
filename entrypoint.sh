#!/bin/env bash
ARGS=""

if [ "$GITHUB_ACTION" != "" ]; then
	ARGS="$ARGS --github"
fi

if [ "$MAPPINGS" != ""]; then
	ARGS="$ARGS --mappings=\"$MAPPINGS\""
fi

if [ "$IF_NO_MATCH_FAIL" = "1" ]; then
	ARGS="$ARGS --require-at-least-one-change"
fi

if [ "$IF_WELL_KNOWN_VARS_MISSING_FAIL" = "1" ]; then
	ARGS="$ARGS --require-one-well-known-var"
fi

if [ "$ENABLE_COMMIT" = "1" ]; then
	ARGS="$ARGS --commit"
fi 

if [ "$COMMIT_TEMPLATE" != "" ]; then
	ARGS="$ARGS --commit-template=\"$COMMIT_TEMPLATE\""
fi

if [ "$COMMITS_SPLIT_UP_BY" != "" ]; then
	ARGS="$ARGS --commit-split-up-by=\"$COMMITS_SPLIT_UP_BY\""
fi

if [ "$UPDATED_FILE_SUFFIX" != "" ]; then
	ARGS="$ARGS --updated-file-suffix=\"$UPDATED_FILE_SUFFIX\""
fi

if [ "$REGISTER_CUSTOM_REGEXES" != "" ]; then
	ARGS="$ARGS --custom-regexes=\"$REGISTER_CUSTOM_REGEXES\""
fi

if [ "$REGISTER_CUSTOM_VARIABLES" != "" ]; then
	ARGS="$ARGS --custom-variables=\"$REGISTER_CUSTOM_VARIABLES\""
fi

if [ "$DIRECTORY" != ""]; then
	ARGS="$ARGS --directory=\"$directory\""
fi

if [ "$DUMP" = "1" ]; then
	ARGS="$ARGS --dump"
fi



./application update-environment $ARGS