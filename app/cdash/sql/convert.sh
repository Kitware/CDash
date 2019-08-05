#!/bin/sh

# transform database setup code from MySQL syntax to PostgreSQL syntax
# (requires SQL-Translator from http://sqlfairy.sourceforge.net)

cd mysql

for in in *.sql; do
    echo "processing \"$in\""
    out=../pgsql/$in
    grep -vi "alter table" $in | sqlt --from MySQL --to PostgreSQL - > $out
    ../convert_alter_table.pl $in >> $out
    ext=$(echo $out | sed 's/\.sql$/.ext.sql/g')

    if test -e $ext; then
        cat $ext >> $out
    fi
done
