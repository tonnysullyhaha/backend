#!/bin/sh

# Installs ZF to library/

cd $(dirname $0);

if [ -d "../library/Zend" ]; then
    echo Zend Framework is already installed
    exit;
fi

echo Installing Zend Framework

tmpDir=/tmp/zf_install/

mkdir -p $tmpDir

zfGit=https://codeload.github.com/zendframework/zf1/zip/master

echo Saving to $tmpDir"zf.zip"
curl $zfGit > $tmpDir"zf.zip"

file $tmpDir"zf.zip"

echo Unzipping...
unzip -q $tmpDir"zf.zip" -d $tmpDir

echo Installing...
cp -R "$tmpDir"/zf1-master/library/Zend ../library/Zend
rm -rf $tmpDir
