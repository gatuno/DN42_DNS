#!/bin/sh
APP=DNS42
CONFFILE=dns42.php
POTFILE=dns42.pot
POFILE=dns42.po
GATUF_PATH=`php -r "require_once('./$APP/conf/path.php'); echo DNS42_PATH;"`
echo "php $GATUF_PATH/extracttemplates.php ./$APP/conf/$CONFFILE ./$APP/gettexttemplates"
echo "xgettext -o $POTFILE -p ./$APP/locale --force-po --from-code=UTF-8 --keyword --keyword=__ --keyword=_n:1,2 -L PHP ./$APP/*.php"
echo "find ./$APP/ -iname \"*.php\" -exec xgettext -o $POTFILE -p ./$APP/locale/ --from-code=UTF-8 -j --keyword --keyword=__ --keyword=_n:1,2 -L PHP {} \;"
echo "for pofile in \`ls ./$APP/locale/*/$POFILE\`; do msgmerge -U \$pofile ./$APP/locale/$POTFILE; done"
echo "rm -R ./$APP/gettexttemplates"

