#!/bin/sh

# transform database setup code from MySQL syntax to PostgreSQL syntax
# (requires SQL-Translator from http://sqlfairy.sourceforge.net)

cd mysql

for sql in *.sql; do
    echo "processing \"$sql\""
    grep -v "alter table" $sql | sqlt --from MySQL --to PostgreSQL - > ../pgsql/$sql
done

cat ../pgsql/cdash-compat.sql >> ../pgsql/cdash.sql
