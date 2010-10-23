(
set -e
src=`pwd`
mkdir /tmp/commonist-wikifarm
cd /tmp/commonist-wikifarm
unzip "$src"/commonist-0.3.43.zip
cd commonist-0.3.43
mkdir -p mwapi/lib
cd mwapi
unzip ../lib/mwapi-src.jar
patch -p0 <"$src"/commonist-0.3.43-wikifarm-1.15.patch
(
  lib=`pwd`/../lib
  export CLASSPATH="$lib/bsh-2.0b2-fixed.jar:$lib/lib-util.jar:$lib/minibpp.jar:$lib/commons-httpclient-3.1.jar:$lib/commons-logging-1.1.jar:$lib/commons-codec-1.3.jar:$lib/jericho-html-3.1.jar"
  ant -f build.xml
)
cp -p build/export/*.jar ../lib/
cd ..
ant -f build.xml
cp -p build/commonist-0.3.43.zip /tmp/commonist-0.3.43-wikifarm-1.15.zip
rm -rf /tmp/commonist-wikifarm
echo
echo Done.
echo
ls -l /tmp/commonist-0.3.43-wikifarm-1.15.zip
)
