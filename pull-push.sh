#!/bin/sh
# status/
# iexpenses/
# mqtt/
# mqtt.web/
# lsi/
# iboot/
# iboot0/
# web2sms/
# diallaktis.vlsi.gr/


#Store local Credentials per directory--It should be executed in each directory
#git config --local credential.helper "store --file=.git/credentials"

#git config --global user.email "mdasyg@ieee.org"

#--> if you have the key .ssh/id_ed25519 that a public key is on github then just
# ---------------------------------------------------------------------------
#git clone git@github.com:mdasyg/localLLM.git
#git branch -M main
#git config --global --add safe.directory .
#touch test
#git add .
#git commit -m "Initial server changes"
#git push -u origin main ||  git push --set-upstream origin main
# ---------------------------------------------------------------------------


#git init

#git remote add origin git@github.com:mdasyg/workfolder.git
#git remote add origin git@github.com:mdasyg/status.git
#git remote add origin git@github.com:mdasyg/iexpenses.git
#git remote set-url origin git@github.com:mdasyg/stasks.git
#git remote set-url origin git@github.com:mdasyg/mqtt.git
#git remote add origin git@github.com:mdasyg/mqtt.web.git
#git remote add origin git@github.com:mdasyg/iboot.git
#git remote add origin git@github.com:mdasyg/ibootNG.git
#git remote add origin git@github.com:mdasyg/web2sms.git

# To use tokens (use https:// not ssh):
#git remote add origin https://github.com/mdasyg/markdown_to_pdf.git
#git remote set-url origin https://github.com/mdasyg/status.git

#git branch -M main

#---do we have files locally to push? or remote to pull?
#git pull origin main ||| git push -u origin main
#git config --global --add safe.directory .
#git status
## 
## echo "# my repository" > README.md
## git add .
## git commit -m "Initial server changes"
#git push -u origin main ||  git push --set-upstream origin main
#
#./pull-push.sh

thedir=`dirname $0`
cdir=`pwd`


cd "$thedir"


GIT_NAME="Minas Dasygenis"
GIT_EMAIL="mdasyg@vlsi.gr"

if ! git config user.name >/dev/null 2>&1; then
    git config --global user.name "$GIT_NAME"
fi

if ! git config user.email >/dev/null 2>&1; then
    git config --global user.email "$GIT_EMAIL"
fi



if git ls-files -ci --exclude-standard -z | xargs -0 -n1 | grep -q .; then
    echo "Found tracked files that are now ignored. Removing..."

    git ls-files -ci --exclude-standard -z | xargs -0 git rm --cached

    echo "Committing changes..."
    git commit -m "Remove no longer tracked ignored files"
    git push
fi


umask 022
git pull origin main
git add .
git commit -m "pull-push automatic commit"
git push

if [ `whoami` != "webdevel" ]; then
    if id www > /dev/null 2>&1; then
        chown -R www:www .
        chmod -R o-rwx .
    elif id www-data > /dev/null 2>&1; then
        chown -R root:www-data .
        chmod -R u=rwX,g=rX,o= .
        for d in data public/cache public/uploads stats; do
            if [ -d "$d" ]; then

                chown -R www-data:www-data "$d"
                chmod -R u=rwX,g=rwX,o=rX "$d"
                find "$d" -type d -exec chmod g+s {} + 2>/dev/null
            fi
        done
    else
        chmod -R o-rwx .
    fi

    ls *.sh > /dev/null 2>&1
    if [ $? -eq 0 ] ;then
        chmod u=rwx,g=rx *.sh 
    fi

    ls *.py > /dev/null 2>&1
    if [ $? -eq 0 ] ;then
        chmod u+rwx,go+rx *.py
    fi
fi

cd $cdir


