#!/bin/env bash
ARGS=""

if [ "$GITHUB_ACTION" != "" ]; then
	ARGS="$ARGS --github"
fi

if [ "$IF_WELL_KNOWN_VARS_MISSING_FAIL" = "1" ]; then
	ARGS="$ARGS --require-well-known-var"
fi

if [ "$DUMP" = "1" ]; then
	ARGS="$ARGS --dump"
fi

./application update-environment $ARGS