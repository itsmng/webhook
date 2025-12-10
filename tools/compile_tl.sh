#!/usr/bin/env sh

for file in $(find locales -name "*.po"); do
    dir=$(dirname "$file")
    base=$(basename "$file" .po)
    msgfmt "$file" -o "$dir/$base.mo"
    echo "Compiled $file to $dir/$base.mo"
done